<?php

namespace Database\Factories;

use App\Enums\CashOperationOrigin;
use App\Enums\CashOperationType;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashOperation>
 */
class CashOperationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cash_register_id' => CashRegister::factory(),
            'user_id' => User::factory(),
            'type' => CashOperationType::In,
            'origin' => CashOperationOrigin::CashReinforcement,
            'payment_method_id' => null,
            'amount' => fake()->randomFloat(2, 1, 200),
            'notes' => null,
        ];
    }
}
