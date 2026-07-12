<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariation>
 */
class ProductVariationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'color' => null,
            'size' => null,
            'ean_gtin' => fake()->unique()->ean13(),
            'product_code' => fake()->unique()->bothify('SKU-#####'),
            'cost_price' => fake()->randomFloat(2, 1, 100),
            'markup' => fake()->randomFloat(2, 10, 100),
            'sale_price' => fake()->randomFloat(2, 10, 200),
            'current_quantity' => fake()->numberBetween(0, 50),
            'min_quantity' => 5,
            'max_quantity' => 100,
        ];
    }
}
