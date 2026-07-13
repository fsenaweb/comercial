<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'active' => $this->active,
            'unit_id' => $this->unit_id,
            'unit' => UnitResource::make($this->whenLoaded('unit')),
            'location' => $this->location,
            'category_id' => $this->category_id,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'subcategory_id' => $this->subcategory_id,
            'subcategory' => SubcategoryResource::make($this->whenLoaded('subcategory')),
            'brand_id' => $this->brand_id,
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'supplier_id' => $this->supplier_id,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'fiscal_fields' => $this->fiscal_fields,
            'variations' => ProductVariationResource::collection($this->whenLoaded('variations')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
