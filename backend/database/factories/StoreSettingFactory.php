<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StoreSetting>
 */
class StoreSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'cnpj' => fake()->numerify('##.###.###/####-##'),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'require_seller_on_sale' => false,
            'auto_open_cash_register' => false,
        ];
    }
}
