<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'mobile_phone' => fake()->numerify('###########'),
            'phone' => null,
            'email' => fake()->unique()->safeEmail(),
            'document' => fake()->numerify('###.###.###-##'),
            'is_company' => false,
            'birth_date' => fake()->date(),
            'zip_code' => fake()->numerify('#####-###'),
            'address' => fake()->streetName(),
            'address_number' => fake()->buildingNumber(),
            'address_complement' => null,
            'neighborhood' => fake()->citySuffix(),
            'city' => fake()->city(),
            'state' => 'SP',
            'notes' => null,
        ];
    }
}
