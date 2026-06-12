<?php

namespace Database\Seeders;

use App\Models\Sale;
use App\Models\User;
use App\Services\CommissionDistributor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CommissionDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'demo.ana@voicecentra.com')->exists()) {
            return; // already seeded
        }

        $admin = User::where('role', 'admin')->firstOrFail();
        $seller1 = User::where('email', 'seller1@seller1.com')->first();

        if (! $seller1) {
            return; // base seeder hasn't run
        }

        $make = function (string $name, string $email, User $sponsor): User {
            return User::factory()->approvedSeller()->withSponsor($sponsor)->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('seller'),
                'phone' => '555-0'.fake()->numberBetween(200, 999),
            ]);
        };

        // 3 levels under seller1
        $ana = $make('Ana Demo', 'demo.ana@voicecentra.com', $seller1);
        $bruno = $make('Bruno Demo', 'demo.bruno@voicecentra.com', $ana);
        $carla = $make('Carla Demo', 'demo.carla@voicecentra.com', $bruno);
        $make('Diego Demo', 'demo.diego@voicecentra.com', $ana);

        $distributor = app(CommissionDistributor::class);

        $submit = function (User $seller, int $cents, int $daysAgo): Sale {
            $sale = new Sale(['amount_cents' => $cents, 'sold_at' => now()->subDays($daysAgo), 'notes' => 'Demo sale']);
            $sale->seller_id = $seller->id;
            $sale->status = 'pending';
            $sale->save();

            return $sale;
        };

        // Approved sales flowing commissions up the chain
        foreach ([
            [$carla, 120_000, 12], [$carla, 80_000, 5],
            [$bruno, 150_000, 20],
            [$ana, 200_000, 25], [$ana, 95_000, 3],
            [$seller1, 175_000, 8],
        ] as [$seller, $cents, $daysAgo]) {
            $distributor->distribute($submit($seller, $cents, $daysAgo), $admin);
        }

        // One pending sale for the admin queue
        $submit($carla, 60_000, 1);
    }
}
