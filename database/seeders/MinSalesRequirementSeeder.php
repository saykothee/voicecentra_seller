<?php

namespace Database\Seeders;

use App\Models\MinSalesRequirement;
use Illuminate\Database\Seeder;

class MinSalesRequirementSeeder extends Seeder
{
    public function run(): void
    {
        if (MinSalesRequirement::count() > 0) {
            return; // idempotent — ranges are fixed, values are edited in the UI
        }

        $brackets = [
            ['min_age' => 18, 'max_age' => 29, 'min_sales' => 10],
            ['min_age' => 30, 'max_age' => 39, 'min_sales' => 8],
            ['min_age' => 40, 'max_age' => 49, 'min_sales' => 6],
            ['min_age' => 50, 'max_age' => 59, 'min_sales' => 5],
            ['min_age' => 60, 'max_age' => 69, 'min_sales' => 4],
            ['min_age' => 70, 'max_age' => 79, 'min_sales' => 3],
            ['min_age' => 80, 'max_age' => null, 'min_sales' => 2],
        ];

        foreach ($brackets as $bracket) {
            MinSalesRequirement::create($bracket);
        }
    }
}
