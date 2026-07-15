<?php

namespace App\Actions\StockEntry;

use App\Actions\AccountsPayable\RegisterAccountsPayableAction;
use App\Actions\Stock\RegisterStockEntryAction;
use App\Models\ProductVariation;
use App\Models\StockEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportNfeStockEntryAction
{
    public function __construct(
        private readonly RegisterStockEntryAction $registerStockEntryAction,
        private readonly RegisterAccountsPayableAction $registerAccountsPayableAction,
    ) {}

    public function execute(array $data, ?UploadedFile $xmlFile, User $user): StockEntry
    {
        return DB::transaction(function () use ($data, $xmlFile, $user) {
            $xmlPath = $xmlFile ? $xmlFile->storeAs('nfe-xml', Str::uuid().'.xml', 'public') : null;

            $stockEntry = StockEntry::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'nfe_number' => $data['nfe_number'] ?? null,
                'nfe_series' => $data['nfe_series'] ?? null,
                'nfe_key' => $data['nfe_key'] ?? null,
                'issue_date' => $data['issue_date'] ?? null,
                'freight_value' => $data['freight_value'] ?? 0,
                'products_total' => $data['products_total'],
                'total_value' => $data['total_value'],
                'xml_path' => $xmlPath,
                'imported_by' => $user->id,
            ]);

            foreach ($data['items'] as $item) {
                $this->registerStockEntryAction->execute([
                    'product_variation_id' => $item['product_variation_id'],
                    'quantity' => $item['quantity'],
                    'origin' => 'stock_entry',
                    'reference_id' => $stockEntry->id,
                ], $user);

                if ($item['update_cost'] ?? false) {
                    $this->updateCost($item['product_variation_id'], (string) $item['unit_cost']);
                }
            }

            if ($data['generate_accounts_payable'] ?? false) {
                $accountsPayable = $this->registerAccountsPayableAction->execute([
                    'supplier_id' => $data['supplier_id'],
                    'description' => "NF-e {$stockEntry->nfe_number} — ".($data['supplier_name'] ?? ''),
                    'total_amount' => $data['total_value'],
                    'stock_entry_id' => $stockEntry->id,
                    'installments' => $data['payable_installments'],
                ], $user);

                $stockEntry->update(['generated_accounts_payable_id' => $accountsPayable->id]);
            }

            return $stockEntry->load(['supplier', 'stockMovements.productVariation.product', 'generatedAccountsPayable.installments']);
        });
    }

    private function updateCost(int $productVariationId, string $unitCost): void
    {
        $variation = ProductVariation::whereKey($productVariationId)->lockForUpdate()->firstOrFail();

        $update = ['cost_price' => $unitCost];

        if ($variation->markup !== null) {
            $factor = bcadd('1', bcdiv((string) $variation->markup, '100', 4), 4);
            $update['sale_price'] = bcmul($unitCost, $factor, 2);
        }

        $variation->update($update);
    }
}
