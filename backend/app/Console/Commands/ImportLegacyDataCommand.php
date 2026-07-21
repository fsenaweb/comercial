<?php

namespace App\Console\Commands;

use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa produtos e clientes do ERP legado (Firebird), a partir dos CSVs
 * gerados por scripts/legacy-import/export-firebird.sh. Ver
 * docs/11-migracao-sistema-legado.md para o mapeamento completo e o
 * histórico de decisões — escopo definido pelo cliente em 2026-07-18:
 * só cadastro de produto e cliente, sem histórico de venda/estoque/caixa.
 *
 * Seguro rodar mais de uma vez: produtos são casados por `product_code`
 * (atualiza em vez de duplicar; diferença de estoque vira um novo
 * stock_movement de ajuste) e clientes por `document` quando presente.
 */
class ImportLegacyDataCommand extends Command
{
    protected $signature = 'legacy:import {path : Pasta com os CSVs gerados por export-firebird.sh}';

    protected $description = 'Importa produtos e clientes do ERP legado (Firebird) a partir dos CSVs exportados';

    /** Duas linhas de "Taxa de Couvert"/"Taxa de Serviço" — resíduo do módulo de restaurante do ERP legado, não usado nesta loja. */
    private const IGNORED_PRODUCT_NAMES = ['TAXA DE COUVERT', 'TAXA DE SERVIÇO'];

    private array $categoryMap = [];

    private array $brandMap = [];

    private array $unitMap = [];

    private ?int $fallbackCategoryId = null;

    private ?int $fallbackUnitId = null;

    public function handle(): int
    {
        $path = rtrim((string) $this->argument('path'), '/');

        $admin = User::where('role', UserRole::Admin)->first();
        if (! $admin) {
            $this->error('Nenhum usuário admin encontrado — necessário para atribuir os movimentos de estoque inicial.');

            return self::FAILURE;
        }

        foreach (['grupo', 'marca', 'unidade', 'produto', 'pessoa'] as $file) {
            if (! is_file("{$path}/{$file}.csv")) {
                $this->error("Arquivo não encontrado: {$path}/{$file}.csv");

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($path, $admin) {
            $this->importCategories("{$path}/grupo.csv");
            $this->importBrands("{$path}/marca.csv");
            $this->importUnits("{$path}/unidade.csv");
            $this->importProducts("{$path}/produto.csv", $admin);
            $this->importCustomers("{$path}/pessoa.csv");
        });

        $this->info('Importação concluída.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function readRow(mixed $handle): array|false
    {
        $row = fgetcsv($handle, 0, '|', "\x07", "\x07");
        if ($row === false) {
            return false;
        }
        // Linha em branco (o isql do Firebird escreve uma antes do primeiro
        // resultado) ou linha sem nenhum campo útil — pula.
        if (count($row) === 1 && trim((string) $row[0]) === '') {
            return $this->readRow($handle);
        }

        return array_map(fn ($v) => trim((string) $v), $row);
    }

    private function importCategories(string $file): void
    {
        $handle = fopen($file, 'r');
        while (($row = $this->readRow($handle)) !== false) {
            [$code, $name] = $row;
            if ($name === '') {
                continue;
            }
            $category = Category::firstOrCreate(['name' => $name]);
            $this->categoryMap[$code] = $category->id;
            $this->fallbackCategoryId ??= $category->id;
        }
        fclose($handle);
        $this->info('Categorias: '.count($this->categoryMap));
    }

    private function importBrands(string $file): void
    {
        $handle = fopen($file, 'r');
        while (($row = $this->readRow($handle)) !== false) {
            [$code, $name] = $row;
            if ($name === '') {
                continue;
            }
            $brand = Brand::firstOrCreate(['name' => $name]);
            $this->brandMap[$code] = $brand->id;
        }
        fclose($handle);
        $this->info('Marcas: '.count($this->brandMap));
    }

    private function importUnits(string $file): void
    {
        $handle = fopen($file, 'r');
        while (($row = $this->readRow($handle)) !== false) {
            [$code, $name] = $row;
            if ($code === '') {
                continue;
            }
            $unit = Unit::firstOrCreate(
                ['abbreviation' => $code],
                ['name' => $name !== '' ? $name : $code],
            );
            $this->unitMap[$code] = $unit->id;
            $this->fallbackUnitId ??= $unit->id;
        }
        fclose($handle);
        $this->info('Unidades: '.count($this->unitMap));
    }

    private function importProducts(string $file, User $admin): void
    {
        $handle = fopen($file, 'r');
        $seenCodes = [];
        $created = 0;
        $updated = 0;

        while (($row = $this->readRow($handle)) !== false) {
            [
                $codigo, $codbarra, $descricao, $referencia, $grupo, $unidade, $fkMarca,
                $prCusto, $margem, $prVenda, $qtdAtual, $qtdMin, $precoAtacado, $qtdAtacado,
                $ativo, $servico,
            ] = $row;

            if ($descricao === '' || in_array($descricao, self::IGNORED_PRODUCT_NAMES, true)) {
                continue;
            }

            $productCode = $this->uniqueProductCode($referencia, $codigo, $seenCodes);
            $eanGtin = $this->normalizeBarcode($codbarra);
            $wholesalePrice = (float) $precoAtacado;
            $wholesaleQty = (int) round((float) $qtdAtacado);

            $productAttributes = [
                'name' => $descricao,
                'type' => $servico === 'S' ? ProductType::Service : ProductType::Product,
                'active' => $ativo === 'S',
                'unit_id' => $this->unitMap[$unidade] ?? $this->fallbackUnitId,
                'category_id' => $this->categoryMap[$grupo] ?? $this->fallbackCategoryId,
                'brand_id' => $this->brandMap[$fkMarca] ?? null,
            ];

            $variationAttributes = [
                'ean_gtin' => $eanGtin,
                'legacy_code' => $codigo,
                'cost_price' => (float) $prCusto,
                'markup' => $this->normalizeMarkup((float) $margem),
                'sale_price' => (float) $prVenda,
                'min_quantity' => (int) round((float) $qtdMin),
                'wholesale_price' => $wholesalePrice > 0 && $wholesaleQty > 0 ? $wholesalePrice : null,
                'wholesale_min_qty' => $wholesalePrice > 0 && $wholesaleQty > 0 ? $wholesaleQty : null,
            ];

            $newQuantity = (int) round((float) $qtdAtual);
            $variation = ProductVariation::withTrashed()->where('product_code', $productCode)->first();

            if ($variation) {
                $variation->product->update($productAttributes);
                $oldQuantity = $variation->current_quantity;
                $variation->update($variationAttributes);
                if ($oldQuantity !== $newQuantity) {
                    StockMovement::create([
                        'product_variation_id' => $variation->id,
                        'type' => StockMovementType::Adjustment,
                        'quantity' => $newQuantity - $oldQuantity,
                        'origin' => 'reimportação sistema legado',
                        'user_id' => $admin->id,
                    ]);
                    $variation->update(['current_quantity' => $newQuantity]);
                }
                $updated++;
            } else {
                $product = Product::create($productAttributes);
                $variation = $product->variations()->create([
                    ...$variationAttributes,
                    'product_code' => $productCode,
                    'current_quantity' => $newQuantity,
                ]);
                StockMovement::create([
                    'product_variation_id' => $variation->id,
                    'type' => StockMovementType::Adjustment,
                    'quantity' => $newQuantity,
                    'origin' => 'estoque inicial (importação sistema legado)',
                    'user_id' => $admin->id,
                ]);
                $created++;
            }
        }
        fclose($handle);
        $this->info("Produtos: {$created} criados, {$updated} atualizados.");
    }

    /** REFERENCIA tem ~387 colisões no legado — desempata anexando o CODIGO original. */
    private function uniqueProductCode(string $referencia, string $codigo, array &$seenCodes): string
    {
        $base = $referencia !== '' ? $referencia : "LEG-{$codigo}";
        if (! isset($seenCodes[$base])) {
            $seenCodes[$base] = true;

            return $base;
        }

        return "{$base}-{$codigo}";
    }

    private function normalizeBarcode(string $value): ?string
    {
        if ($value === '' || strcasecmp($value, 'SEM GTIN') === 0) {
            return null;
        }

        return $value;
    }

    /**
     * `product_variations.markup` é `decimal(7,2)` (máx. ~99999.99). 148 dos
     * 13.280 produtos do legado têm MARGEM com valor absurdo (erro de
     * digitação no sistema antigo, ex.: 9.588.000%) — fora da faixa,
     * `markup` vira null em vez de derrubar a importação inteira.
     */
    private function normalizeMarkup(float $value): ?float
    {
        return abs($value) < 100000 ? $value : null;
    }

    private function importCustomers(string $file): void
    {
        $handle = fopen($file, 'r');
        $created = 0;
        $updated = 0;

        while (($row = $this->readRow($handle)) !== false) {
            [
                $codigo, $razao, $fantasia, $cnpj, $tipo, $endereco, $numero, $complemento,
                $bairro, $municipio, $uf, $cep, $fone1, $celular1, $email1, $ativo,
            ] = $row;

            $name = $fantasia !== '' ? $fantasia : $razao;
            if ($name === '') {
                continue;
            }

            $attributes = [
                'name' => $name,
                'mobile_phone' => $celular1 !== '' ? $celular1 : null,
                'phone' => $fone1 !== '' ? $fone1 : null,
                'email' => $email1 !== '' ? $email1 : null,
                'document' => $cnpj !== '' ? $cnpj : null,
                'is_company' => strcasecmp($tipo, 'JURÍDICA') === 0,
                'zip_code' => $cep !== '' ? $cep : null,
                'address' => $endereco !== '' ? $endereco : null,
                'address_number' => $numero !== '' ? $numero : null,
                'address_complement' => $complemento !== '' ? $complemento : null,
                'neighborhood' => $bairro !== '' ? $bairro : null,
                'city' => $municipio !== '' ? $municipio : null,
                'state' => $uf !== '' ? substr($uf, 0, 2) : null,
            ];

            $existing = $cnpj !== ''
                ? Customer::withTrashed()->where('document', $cnpj)->first()
                : Customer::withTrashed()->where('name', $name)->whereNull('document')->first();

            if ($existing) {
                $existing->update($attributes);
                $updated++;
            } else {
                Customer::create($attributes);
                $created++;
            }
        }
        fclose($handle);
        $this->info("Clientes: {$created} criados, {$updated} atualizados.");
    }
}
