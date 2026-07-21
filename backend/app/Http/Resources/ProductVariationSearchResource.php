<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload enxuto para busca de produto no PDV/Etiquetas (GET
 * /product-variations/search|lookup) — só os campos usados no carrinho e na
 * impressão de etiqueta, sem os relacionamentos completos de ProductResource
 * (categoria/marca/fornecedor etc.). Ver docs/11-migracao-sistema-legado.md,
 * achado de escala com o catálogo real (13 mil produtos).
 */
class ProductVariationSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product->name,
            'color' => $this->color,
            'size' => $this->size,
            'ean_gtin' => $this->ean_gtin,
            'product_code' => $this->product_code,
            'sale_price' => $this->sale_price,
            'current_quantity' => $this->current_quantity,
            'wholesale_min_qty' => $this->wholesale_min_qty,
            'wholesale_price' => $this->wholesale_price,
        ];
    }
}
