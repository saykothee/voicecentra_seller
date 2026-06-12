<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_id' => User::factory()->approvedSeller(),
            'amount_cents' => fake()->numberBetween(50_00, 5_000_00),
            'sold_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'status' => 'pending',
            'notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved', 'approved_at' => now()]);
    }
}
