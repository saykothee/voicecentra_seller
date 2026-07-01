<?php

use App\Models\Sale;
use App\Services\ClientPaymentStatus;
use Database\Seeders\ClientPaymentsDemoSeeder;
use Illuminate\Support\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

test('the demo seeder produces all three payment statuses', function () {
    Carbon::setTestNow('2026-06-29'); // a day with room for the late/due spread

    $this->seed(ClientPaymentsDemoSeeder::class);

    $sales = Sale::whereNotNull('client_id')->with('seller:id,name')->get();
    $report = (new ClientPaymentStatus())->report($sales, Carbon::today());

    expect($report)->toHaveCount(10);
    expect($report->where('status', 'late')->count())->toBe(4);
    expect($report->where('status', 'due_today')->count())->toBe(3);
    expect($report->where('status', 'to_be_paid')->count())->toBe(3);
    expect($report->firstWhere('client_id', 'CUST-1010')['on_trial'])->toBeTrue();

    // 10 clients x 3 monthly payments.
    expect(Sale::whereNotNull('client_id')->count())->toBe(30);
});

test('re-running the demo seeder refreshes rather than duplicates', function () {
    Carbon::setTestNow('2026-06-29');

    $this->seed(ClientPaymentsDemoSeeder::class);
    $this->seed(ClientPaymentsDemoSeeder::class);

    expect(Sale::whereNotNull('client_id')->count())->toBe(30);
});

test('the demo seeder is not wired into the default DatabaseSeeder', function () {
    expect(file_get_contents(database_path('seeders/DatabaseSeeder.php')))
        ->not->toContain('ClientPaymentsDemoSeeder');
});
