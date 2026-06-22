<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'VoiceCentra Admin',
            'email' => env('ADMIN_EMAIL', 'admin@voicecentra.com'),
            'password' => \Illuminate\Support\Facades\Hash::make(env('ADMIN_PASSWORD', 'ChangeMe123!')),
            'role' => 'admin',
            'status' => 'approved',
            'phone' => null,
        ]);

        // Approved seller — can log in and reach the seller dashboard immediately.
        User::factory()->approvedSeller()->create([
            'name' => 'Seller One',
            'email' => 'seller1@seller1.com',
            'password' => \Illuminate\Support\Facades\Hash::make('seller'),
            'phone' => '555-0101',
        ]);

        // Pending seller — sits in the approval queue so you can demo admin approve/reject.
        User::factory()->pending()->create([
            'name' => 'Seller Two',
            'email' => 'seller2@seller2.com',
            'password' => \Illuminate\Support\Facades\Hash::make('seller'),
            'phone' => '555-0102',
        ]);

        $this->call(CommissionDemoSeeder::class);
        $this->call(MinSalesRequirementSeeder::class);
    }
}
