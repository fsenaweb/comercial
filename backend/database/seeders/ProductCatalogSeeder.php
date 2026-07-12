<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Catálogo de demonstração para o segmento-alvo (autopeças/motopeças,
 * ferragens/parafusaria) — ver CLAUDE.md. Não roda automaticamente no
 * DatabaseSeeder principal; disparar sob demanda:
 *   docker compose exec php-fpm php artisan db:seed --class=ProductCatalogSeeder
 */
class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->admin()->create();

        $unitUn = Unit::firstOrCreate(['abbreviation' => 'UN'], ['name' => 'Unidade']);
        $unitL = Unit::firstOrCreate(['abbreviation' => 'L'], ['name' => 'Litro']);
        $unitCx = Unit::firstOrCreate(['abbreviation' => 'CX'], ['name' => 'Caixa']);

        $brandNames = ['Vonder', 'Tramontina', 'Bosch', 'Mobil', 'Castrol', 'Fras-le', 'NGK'];
        $brands = collect($brandNames)->mapWithKeys(fn (string $name) => [$name => Brand::firstOrCreate(['name' => $name])]);

        $categoryFerramentas = Category::firstOrCreate(
            ['name' => 'Ferramentas'],
            ['description' => 'Ferramentas manuais e de uso geral para oficina e casa.'],
        );
        $categoryParafusos = Category::firstOrCreate(
            ['name' => 'Parafusos e Fixadores'],
            ['description' => 'Parafusos, porcas, arruelas e buchas.'],
        );
        $categoryOleos = Category::firstOrCreate(
            ['name' => 'Óleos e Fluidos'],
            ['description' => 'Óleos automotivos e fluidos veiculares.'],
        );
        $categoryMotor = Category::firstOrCreate(
            ['name' => 'Peças de Motor'],
            ['description' => 'Peças de reposição para motor de carro e moto.'],
        );

        $subParafusos = Subcategory::firstOrCreate(['category_id' => $categoryParafusos->id, 'name' => 'Parafusos']);
        $subPorcasArruelas = Subcategory::firstOrCreate(['category_id' => $categoryParafusos->id, 'name' => 'Porcas e Arruelas']);
        $subOleoMotor = Subcategory::firstOrCreate(['category_id' => $categoryOleos->id, 'name' => 'Óleo de Motor']);
        $subFluidos = Subcategory::firstOrCreate(['category_id' => $categoryOleos->id, 'name' => 'Fluidos e Aditivos']);
        $subMotorCarro = Subcategory::firstOrCreate(['category_id' => $categoryMotor->id, 'name' => 'Carro']);
        $subMotorMoto = Subcategory::firstOrCreate(['category_id' => $categoryMotor->id, 'name' => 'Moto']);

        $products = [
            // Ferramentas
            ['name' => 'Chave de Fenda 6mm', 'category' => $categoryFerramentas, 'sub' => null, 'brand' => 'Vonder', 'unit' => $unitUn, 'code' => 'FER-CF-6MM', 'cost' => 8.50, 'price' => 18.90, 'qty' => 40, 'min' => 8],
            ['name' => 'Chave Phillips 5mm', 'category' => $categoryFerramentas, 'sub' => null, 'brand' => 'Vonder', 'unit' => $unitUn, 'code' => 'FER-CP-5MM', 'cost' => 8.90, 'price' => 19.90, 'qty' => 35, 'min' => 8],
            ['name' => 'Alicate Universal 8"', 'category' => $categoryFerramentas, 'sub' => null, 'brand' => 'Tramontina', 'unit' => $unitUn, 'code' => 'FER-ALI-8', 'cost' => 22.00, 'price' => 44.90, 'qty' => 20, 'min' => 5],
            ['name' => 'Martelo Unha 27mm', 'category' => $categoryFerramentas, 'sub' => null, 'brand' => 'Tramontina', 'unit' => $unitUn, 'code' => 'FER-MAR-27', 'cost' => 18.50, 'price' => 36.90, 'qty' => 18, 'min' => 4],
            ['name' => 'Trena 5m', 'category' => $categoryFerramentas, 'sub' => null, 'brand' => 'Vonder', 'unit' => $unitUn, 'code' => 'FER-TRE-5M', 'cost' => 12.00, 'price' => 24.90, 'qty' => 25, 'min' => 5],
            ['name' => 'Jogo de Chaves Allen 9 Peças', 'category' => $categoryFerramentas, 'sub' => null, 'brand' => 'Tramontina', 'unit' => $unitUn, 'code' => 'FER-ALL-9P', 'cost' => 24.00, 'price' => 49.90, 'qty' => 15, 'min' => 3],

            // Parafusos e Fixadores
            ['name' => 'Parafuso Phillips 4x30mm Zincado', 'category' => $categoryParafusos, 'sub' => $subParafusos, 'brand' => null, 'unit' => $unitCx, 'code' => 'PRF-PH-430', 'cost' => 9.00, 'price' => 19.90, 'qty' => 60, 'min' => 10],
            ['name' => 'Parafuso Sextavado M8x40mm', 'category' => $categoryParafusos, 'sub' => $subParafusos, 'brand' => null, 'unit' => $unitCx, 'code' => 'PRF-SX-M840', 'cost' => 14.00, 'price' => 28.90, 'qty' => 45, 'min' => 10],
            ['name' => 'Porca Sextavada M8', 'category' => $categoryParafusos, 'sub' => $subPorcasArruelas, 'brand' => null, 'unit' => $unitCx, 'code' => 'PRF-PO-M8', 'cost' => 7.00, 'price' => 15.90, 'qty' => 55, 'min' => 10],
            ['name' => 'Arruela de Pressão M8', 'category' => $categoryParafusos, 'sub' => $subPorcasArruelas, 'brand' => null, 'unit' => $unitCx, 'code' => 'PRF-AR-M8', 'cost' => 5.50, 'price' => 12.90, 'qty' => 50, 'min' => 10],
            ['name' => 'Bucha de Nylon S6', 'category' => $categoryParafusos, 'sub' => $subParafusos, 'brand' => null, 'unit' => $unitCx, 'code' => 'PRF-BU-S6', 'cost' => 6.00, 'price' => 13.90, 'qty' => 40, 'min' => 8],

            // Óleos e Fluidos
            ['name' => 'Óleo de Motor 5W30 Sintético 1L', 'category' => $categoryOleos, 'sub' => $subOleoMotor, 'brand' => 'Mobil', 'unit' => $unitL, 'code' => 'OLE-5W30-SIN', 'cost' => 32.00, 'price' => 54.90, 'qty' => 24, 'min' => 6],
            ['name' => 'Óleo de Motor 20W50 Mineral 1L', 'category' => $categoryOleos, 'sub' => $subOleoMotor, 'brand' => 'Castrol', 'unit' => $unitL, 'code' => 'OLE-20W50-MIN', 'cost' => 18.00, 'price' => 32.90, 'qty' => 30, 'min' => 6],
            ['name' => 'Óleo de Câmbio ATF 1L', 'category' => $categoryOleos, 'sub' => $subFluidos, 'brand' => 'Castrol', 'unit' => $unitL, 'code' => 'OLE-ATF-1L', 'cost' => 22.00, 'price' => 39.90, 'qty' => 16, 'min' => 4],
            ['name' => 'Fluido de Freio DOT4 500ml', 'category' => $categoryOleos, 'sub' => $subFluidos, 'brand' => 'Bosch', 'unit' => $unitUn, 'code' => 'OLE-DOT4-500', 'cost' => 14.00, 'price' => 26.90, 'qty' => 20, 'min' => 5],
            ['name' => 'Aditivo de Radiador 1L', 'category' => $categoryOleos, 'sub' => $subFluidos, 'brand' => 'Mobil', 'unit' => $unitL, 'code' => 'OLE-ADT-RAD', 'cost' => 11.00, 'price' => 22.90, 'qty' => 18, 'min' => 4],

            // Peças de Motor — Carro
            ['name' => 'Filtro de Óleo Carro', 'category' => $categoryMotor, 'sub' => $subMotorCarro, 'brand' => 'Bosch', 'unit' => $unitUn, 'code' => 'MOT-CAR-FO', 'cost' => 15.00, 'price' => 29.90, 'qty' => 22, 'min' => 5],
            ['name' => 'Vela de Ignição Carro', 'category' => $categoryMotor, 'sub' => $subMotorCarro, 'brand' => 'NGK', 'unit' => $unitUn, 'code' => 'MOT-CAR-VI', 'cost' => 12.00, 'price' => 24.90, 'qty' => 40, 'min' => 8],
            ['name' => 'Correia Dentada', 'category' => $categoryMotor, 'sub' => $subMotorCarro, 'brand' => 'Bosch', 'unit' => $unitUn, 'code' => 'MOT-CAR-CD', 'cost' => 45.00, 'price' => 89.90, 'qty' => 10, 'min' => 2],
            ['name' => 'Junta do Cabeçote', 'category' => $categoryMotor, 'sub' => $subMotorCarro, 'brand' => null, 'unit' => $unitUn, 'code' => 'MOT-CAR-JC', 'cost' => 38.00, 'price' => 74.90, 'qty' => 8, 'min' => 2],

            // Peças de Motor — Moto
            ['name' => 'Filtro de Óleo Moto', 'category' => $categoryMotor, 'sub' => $subMotorMoto, 'brand' => 'Bosch', 'unit' => $unitUn, 'code' => 'MOT-MOT-FO', 'cost' => 10.00, 'price' => 19.90, 'qty' => 26, 'min' => 5],
            ['name' => 'Vela de Ignição Moto', 'category' => $categoryMotor, 'sub' => $subMotorMoto, 'brand' => 'NGK', 'unit' => $unitUn, 'code' => 'MOT-MOT-VI', 'cost' => 9.00, 'price' => 18.90, 'qty' => 45, 'min' => 10],
            ['name' => 'Corrente de Transmissão Moto', 'category' => $categoryMotor, 'sub' => $subMotorMoto, 'brand' => 'Fras-le', 'unit' => $unitUn, 'code' => 'MOT-MOT-CT', 'cost' => 55.00, 'price' => 109.90, 'qty' => 9, 'min' => 2],
            ['name' => 'Kit Relação Moto (Coroa/Pinhão/Corrente)', 'category' => $categoryMotor, 'sub' => $subMotorMoto, 'brand' => 'Fras-le', 'unit' => $unitUn, 'code' => 'MOT-MOT-KR', 'cost' => 95.00, 'price' => 179.90, 'qty' => 6, 'min' => 2],
        ];

        foreach ($products as $data) {
            $product = Product::firstOrCreate(
                ['name' => $data['name']],
                [
                    'type' => ProductType::Product,
                    'unit_id' => $data['unit']->id,
                    'category_id' => $data['category']->id,
                    'subcategory_id' => $data['sub']?->id,
                    'brand_id' => $data['brand'] ? $brands[$data['brand']]->id : null,
                ],
            );

            if ($product->variations()->exists()) {
                continue;
            }

            $variation = $product->variations()->create([
                'product_code' => $data['code'],
                'cost_price' => $data['cost'],
                'sale_price' => $data['price'],
                'current_quantity' => $data['qty'],
                'min_quantity' => $data['min'],
            ]);

            StockMovement::create([
                'product_variation_id' => $variation->id,
                'type' => StockMovementType::Adjustment,
                'quantity' => $data['qty'],
                'origin' => 'estoque inicial',
                'user_id' => $admin->id,
            ]);
        }
    }
}
