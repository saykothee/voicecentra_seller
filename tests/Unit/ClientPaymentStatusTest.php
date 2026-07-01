<?php

use App\Models\Sale;
use App\Services\ClientPaymentStatus;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

function svc(): ClientPaymentStatus
{
    return new ClientPaymentStatus();
}

/** Build an unsaved Sale (casts still apply); status is not mass-assignable. */
function mkSale(string $soldAt, bool $paid, ?string $paidAt = null, bool $trial = false, string $status = 'approved'): Sale
{
    $sale = new Sale([
        'client_id' => 'C1',
        'amount_cents' => 10000,
        'sold_at' => $soldAt,
        'paid_at' => $paidAt,
        'paid' => $paid,
        'trial' => $trial,
    ]);
    $sale->status = $status;

    return $sale;
}

test('billing day comes from the first sale', function () {
    $r = svc()->forClient(collect([
        mkSale('2026-03-15', true, '2026-03-15'),
        mkSale('2026-01-21', true, '2026-01-21'),
    ]), Carbon::parse('2026-06-29'));

    expect($r['billing_day'])->toBe(21);
});

test('a client who paid the current cycle is to_be_paid with next due in the future', function () {
    $r = svc()->forClient(collect([
        mkSale('2026-01-21', true, '2026-01-21'),
        mkSale('2026-06-21', true, '2026-06-21'),
    ]), Carbon::parse('2026-06-29'));

    expect($r['status'])->toBe('to_be_paid');
    expect($r['next_due_date']->format('Y-m-d'))->toBe('2026-07-21');
    expect($r['days_late'])->toBe(0);
});

test('a client past the billing date without a payment this cycle is late', function () {
    $r = svc()->forClient(collect([
        mkSale('2026-01-21', true, '2026-01-21'),
        mkSale('2026-05-21', true, '2026-05-21'),
    ]), Carbon::parse('2026-06-29'));

    expect($r['status'])->toBe('late');
    expect($r['current_due_date']->format('Y-m-d'))->toBe('2026-06-21');
    expect($r['days_late'])->toBe(8); // 21 -> 29
});

test('a client whose billing date is today and unpaid is due_today', function () {
    $r = svc()->forClient(collect([
        mkSale('2026-05-21', true, '2026-05-21'),
    ]), Carbon::parse('2026-06-21'));

    expect($r['status'])->toBe('due_today');
});

test('an unpaid sale does not cover the cycle (paid flag is required)', function () {
    $r = svc()->forClient(collect([
        mkSale('2026-01-21', true, '2026-01-21'),
        mkSale('2026-06-21', false), // recorded but not paid
    ]), Carbon::parse('2026-06-29'));

    expect($r['status'])->toBe('late');
    expect($r['paid_count'])->toBe(1);
});

test('a refunded payment does not count toward being current', function () {
    $r = svc()->forClient(collect([
        mkSale('2026-01-21', true, '2026-01-21'),
        mkSale('2026-06-21', true, '2026-06-21', status: 'refunded'),
    ]), Carbon::parse('2026-06-29'));

    expect($r['status'])->toBe('late');
});

test('a trial payment counts but flags the client as on trial', function () {
    $r = svc()->forClient(collect([
        mkSale('2026-06-21', true, '2026-06-21', trial: true),
    ]), Carbon::parse('2026-06-29'));

    expect($r['status'])->toBe('to_be_paid'); // trial payment covers the cycle
    expect($r['on_trial'])->toBeTrue();
});

test('the billing day is clamped to the length of short months', function () {
    $r = svc()->forClient(collect([
        mkSale('2026-01-31', true, '2026-01-31'),
    ]), Carbon::parse('2026-02-28'));

    // 2026 is not a leap year, so February's billing date clamps to the 28th.
    expect($r['current_due_date']->format('Y-m-d'))->toBe('2026-02-28');
    expect($r['status'])->toBe('due_today');
});
