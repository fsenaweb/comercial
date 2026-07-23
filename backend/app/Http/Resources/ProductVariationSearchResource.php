<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload enxuto para busca de produto no PDV/Etiquetas/Estoque/Crediário
 * (GET /product-variations/search|lookup), sem os relacionamentos completos
 * de ProductResource (categoria/marca/fornecedor etc.). Ver
 * docs/11-migracao-sistema-legado.md, achado de escala com o catálogo real
 * (13 mil produtos).
 *
 * `max_quantity`/`markup` entraram em 2026-07-21: Entradas/Ajuste de Estoque
 * e Importação de NF-e usavam um seletor F2 próprio que carregava todo o
 * catálogo via `GET /products` (sem busca) em vez deste endpoint — desde que
 * `GET /products` passou a ser paginado (achado de escala acima), esses
 * seletores só "buscavam" dentro da primeira página carregada. Migrados pra
 * este endpoint, só faltavam esses dois campos que eles usam e o payload
 * enxuto original não tinha.
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
            'code' => $this->code,
            'sale_price' => $this->sale_price,
            'current_quantity' => $this->current_quantity,
            'max_quantity' => $this->max_quantity,
            'markup' => $this->markup,
            'wholesale_min_qty' => $this->wholesale_min_qty,
            'wholesale_price' => $this->wholesale_price,
        ];
    }
}
