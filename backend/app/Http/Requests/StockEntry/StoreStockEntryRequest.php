<?php

namespace App\Http\Requests\StockEntry;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'xml' => ['nullable', 'file', 'mimetypes:text/xml,application/xml', 'max:5120'],
            'supplier_id' => ['required_if:generate_accounts_payable,true', 'nullable', 'integer', 'exists:suppliers,id'],
            'supplier_name' => ['nullable', 'string'],
            'nfe_number' => ['nullable', 'string', 'max:255'],
            'nfe_series' => ['nullable', 'string', 'max:255'],
            'nfe_key' => ['nullable', 'string', 'max:44'],
            'issue_date' => ['nullable', 'date'],
            'freight_value' => ['nullable', 'numeric', 'min:0'],
            'products_total' => ['required', 'numeric', 'min:0'],
            'total_value' => ['required', 'numeric', 'min:0.01'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variation_id' => ['required', 'integer', 'exists:product_variations,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.update_cost' => ['boolean'],
            'generate_accounts_payable' => ['boolean'],
            'payable_installments' => ['required_if:generate_accounts_payable,true', 'array'],
            'payable_installments.*.number' => ['required_with:payable_installments', 'integer', 'min:1'],
            'payable_installments.*.amount' => ['required_with:payable_installments', 'numeric', 'min:0.01'],
            'payable_installments.*.due_date' => ['required_with:payable_installments', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'products_total.required' => 'Informe o total dos produtos.',
            'total_value.required' => 'Informe o valor total da nota.',
            'total_value.min' => 'O valor total deve ser maior que zero.',
            'items.required' => 'A nota não tem itens para importar.',
            'items.*.product_variation_id.required' => 'Selecione o produto correspondente a cada item da nota.',
            'items.*.product_variation_id.exists' => 'Produto não encontrado.',
            'items.*.quantity.required' => 'Informe a quantidade de cada item.',
            'payable_installments.required_if' => 'Informe as parcelas da conta a pagar.',
            'supplier_id.required_if' => 'Selecione o fornecedor para gerar a conta a pagar.',
        ];
    }
}
