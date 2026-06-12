<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'seller',
            'status' => 'pending',
            'phone' => fake()->numerify('555-####'),
            'depth' => 1,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin', 'status' => 'approved']);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['role' => 'seller', 'status' => 'pending']);
    }

    public function approvedSeller(): static
    {
        return $this->state(fn () => [
            'role' => 'seller',
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function withSponsor(\App\Models\User $sponsor): static
    {
        return $this->state(fn () => [
            'parent_id' => $sponsor->id,
            'depth' => $sponsor->depth + 1,
        ]);
    }
}
