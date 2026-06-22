<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExternalSaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_id' => User::factory()->approvedSeller(),
            'sale_date' => now()->subDays(fake()->numberBetween(0, 30))->toDateString(),
            'paid_at' => now(),
            'amount_cents' => fake()->numberBetween(1000, 500000),
            'paid' => true,
            'free_trial' => false,
        ];
    }
}
