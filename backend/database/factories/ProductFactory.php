<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'type' => ProductType::Product,
            'unit_id' => Unit::factory(),
            'location' => null,
            'category_id' => Category::factory(),
            'subcategory_id' => null,
            'brand_id' => null,
        ];
    }

    public function withBrand(): static
    {
        return $this->state(fn () => ['brand_id' => Brand::factory()]);
    }

    public function withSubcategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'subcategory_id' => Subcategory::factory()->state(['category_id' => $attributes['category_id']]),
        ]);
    }
}
