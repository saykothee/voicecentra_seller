<?php

use App\Services\CommissionRampProjector;
use App\Services\RampSchedule;
use Tests\TestCase;

uses(TestCase::class);

function projector(): CommissionRampProjector
{
    return new CommissionRampProjector();
}

function schedule(int $months = 6): array
{
    return (new RampSchedule())->forMonths($months);
}

/** A monthly map with the same ACTIVE (cumulative) base every month. */
function activeMonths(int $volumeCents, int $quantity, int $sellerFull, int $chainFull, int $months = 6): array
{
    $rows = [];
    for ($m = 1; $m <= $months; $m++) {
        $rows[$m] = [
            'active_volume_cents' => $volumeCents,
            'active_quantity' => $quantity,
            'seller_full_cents' => $sellerFull,
            'chain_full_cents' => $chainFull,
        ];
    }

    return $rows;
}

test('the chain earns full commission every month from month 2 and the seller cut ramps', function () {
    // Active base constant: $1,000 volume, 1 sale, seller full $80, chain full $70.
    $rows = projector()->project(activeMonths(100_000, 1, 8_000, 7_000), schedule(6));

    expect($rows)->toHaveCount(6);
    expect(array_column($rows, 'month'))->toBe([1, 2, 3, 4, 5, 6]);

    // Month 1: nobody earns.
    expect($rows[0])->toMatchArray(['seller_cents' => 0, 'chain_cents' => 0, 'total_cents' => 0]);

    // Months 2-4: seller flat $50 x 1 active sale, chain full $70 — every month.
    foreach ([1, 2, 3] as $i) {
        expect($rows[$i])->toMatchArray(['seller_cents' => 5_000, 'chain_cents' => 7_000, 'total_cents' => 12_000]);
    }

    // Months 5-6: seller full $80, chain full $70 — every month.
    foreach ([4, 5] as $i) {
        expect($rows[$i])->toMatchArray(['seller_cents' => 8_000, 'chain_cents' => 7_000, 'total_cents' => 15_000]);
    }
});

test('the flat stipend is $50 per active sale, paid again every flat month', function () {
    $rows = projector()->project(activeMonths(1_000_000, 10, 80_000, 70_000), schedule(6));

    expect($rows[1]['seller_cents'])->toBe(50_000); // month 2: $50 x 10
    expect($rows[3]['seller_cents'])->toBe(50_000); // month 4: $50 x 10 again
    expect($rows[4]['seller_cents'])->toBe(80_000); // month 5: full cut
    expect($rows[1]['chain_cents'])->toBe(70_000);  // chain full every month from 2
});

test('a growing active base raises later months', function () {
    $months = activeMonths(100_000, 1, 8_000, 7_000);
    // From month 5 a second sale is active: base $2,000, 2 sales, seller full $160, chain $140.
    foreach ([5, 6] as $m) {
        $months[$m] = ['active_volume_cents' => 200_000, 'active_quantity' => 2, 'seller_full_cents' => 16_000, 'chain_full_cents' => 14_000];
    }

    $rows = projector()->project($months, schedule(6));

    expect($rows[1]['seller_cents'])->toBe(5_000);   // month 2 flat: $50 x 1 active sale
    expect($rows[4]['seller_cents'])->toBe(16_000);  // month 5 full cut on the bigger base
    expect($rows[4]['chain_cents'])->toBe(14_000);   // chain on the bigger base
});

test('it splits the seller cut into separate flat and commission components', function () {
    $rows = projector()->project(activeMonths(100_000, 1, 8_000, 7_000), schedule(6));

    expect($rows[0])->toMatchArray(['seller_flat_cents' => 0, 'seller_commission_cents' => 0]);     // M1 none
    expect($rows[1])->toMatchArray(['seller_flat_cents' => 5_000, 'seller_commission_cents' => 0]); // M2 flat only
    expect($rows[4])->toMatchArray(['seller_flat_cents' => 0, 'seller_commission_cents' => 8_000]); // M5 commission only

    // seller_cents stays the sum of the two parts.
    expect($rows[1]['seller_cents'])->toBe(5_000);
    expect($rows[4]['seller_cents'])->toBe(8_000);
});

test('it projects any number of months, staying full after month 5', function () {
    $rows = projector()->project(activeMonths(100_000, 1, 8_000, 7_000, 12), schedule(12));

    expect($rows)->toHaveCount(12);
    expect($rows[11])->toMatchArray([
        'month' => 12,
        'seller_flat_cents' => 0,
        'seller_commission_cents' => 8_000, // full commission, month 12
        'chain_cents' => 7_000,
    ]);
});

test('a month with no active sales earns nothing', function () {
    $rows = projector()->project(activeMonths(0, 0, 0, 0), schedule(6));

    expect($rows[1])->toMatchArray(['seller_cents' => 0, 'chain_cents' => 0, 'total_cents' => 0]); // flat month
    expect($rows[4])->toMatchArray(['seller_cents' => 0, 'chain_cents' => 0, 'total_cents' => 0]); // full month
});

test('it exposes the active base for display', function () {
    $rows = projector()->project(activeMonths(37_035, 3, 0, 0), schedule(6));

    expect($rows[0])->toMatchArray(['active_volume_cents' => 37_035, 'active_quantity' => 3]);
});
