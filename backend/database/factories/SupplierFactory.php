<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'corporate_name' => fake()->unique()->company(),
            'trade_name' => fake()->companySuffix(),
            'mobile_phone' => fake()->numerify('###########'),
            'phone' => null,
            'email' => fake()->unique()->safeEmail(),
            'document' => fake()->numerify('##.###.###/####-##'),
            'is_company' => true,
            'state_registration' => fake()->numerify('#########'),
            'address' => fake()->streetName(),
            'zip_code' => fake()->numerify('#####-###'),
            'address_number' => fake()->buildingNumber(),
            'address_complement' => null,
            'neighborhood' => fake()->citySuffix(),
            'city' => fake()->city(),
            'state' => 'SP',
            'notes' => null,
        ];
    }
}
