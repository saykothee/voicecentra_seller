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

        User::factory()->pending()->create([
            'name' => 'Demo Pending Seller',
            'email' => 'pending@voicecentra.com',
        ]);

        User::factory()->approvedSeller()->create([
            'name' => 'Demo Approved Seller',
            'email' => 'approved@voicecentra.com',
        ]);
    }
}
