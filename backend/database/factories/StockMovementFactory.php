<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_variation_id' => ProductVariation::factory(),
            'type' => StockMovementType::Adjustment,
            'quantity' => fake()->numberBetween(1, 20),
            'origin' => 'manual',
            'reference_id' => null,
            'user_id' => User::factory(),
        ];
    }
}
