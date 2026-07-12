<?php

namespace Database\Factories;

use App\Enums\CashRegisterStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashRegister>
 */
class CashRegisterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'opened_at' => now(),
            'opening_amount' => fake()->randomFloat(2, 0, 500),
            'status' => CashRegisterStatus::Open,
            'closed_at' => null,
            'closing_amount' => null,
            'opened_by' => User::factory()->admin(),
            'closed_by' => null,
            'notes' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CashRegisterStatus::Open,
            'closed_at' => null,
            'closing_amount' => null,
            'closed_by' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CashRegisterStatus::Closed,
            'closed_at' => now(),
            'closing_amount' => fake()->randomFloat(2, 0, 500),
            'closed_by' => User::factory()->admin(),
        ]);
    }
}
