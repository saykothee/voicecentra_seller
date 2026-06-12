<?php

use App\Services\CommissionCalculator;
use Tests\TestCase;

uses(TestCase::class);

function calc(): CommissionCalculator
{
    return new CommissionCalculator();
}

test('full active chain on a $1000 sale splits exactly', function () {
    $r = calc()->calculate(100_000, [
        1 => true, 2 => true, 3 => true, 4 => true, 5 => true,
        6 => true, 7 => true, 8 => true, 9 => true,
    ]);

    expect($r['seller_cents'])->toBe(10_000);                 // 10%
    expect($r['levels'][1]['amount_cents'])->toBe(5_000);     // 5%
    expect($r['levels'][2]['amount_cents'])->toBe(2_500);
    expect($r['levels'][3]['amount_cents'])->toBe(1_250);
    expect($r['levels'][4]['amount_cents'])->toBe(625);
    expect($r['levels'][5]['amount_cents'])->toBe(312);       // 312.5 floored
    expect($r['levels'][6]['amount_cents'])->toBe(156);
    expect($r['levels'][7]['amount_cents'])->toBe(78);
    expect($r['levels'][8]['amount_cents'])->toBe(39);
    expect($r['levels'][9]['amount_cents'])->toBe(19);
    expect($r['total_charge_cents'])->toBe(19_980);           // floor(100000*1023/5120)
    expect($r['pool_rounding_cents'])->toBe(1);
    expect($r['pool_total_cents'])->toBe(1);
    expect(collect($r['levels'])->every(fn ($l) => $l['paid']))->toBeTrue();
});

test('a short chain sends missing levels to the pool', function () {
    $r = calc()->calculate(50_000, [1 => true, 2 => true, 3 => true]); // only 3 uplines

    expect($r['levels'][3]['paid'])->toBeTrue();
    expect($r['levels'][4]['paid'])->toBeFalse();
    expect($r['levels'][4]['pool_reason'])->toBe('no_upline');
    // missing L4..L9: 312+156+78+39+19+9 = 613; total 9990; paid 9375; rounding 2
    expect($r['total_charge_cents'])->toBe(9_990);
    expect($r['pool_total_cents'])->toBe(615);
});

test('inactive uplines at level 4+ are skipped; levels 1-3 pay even if inactive', function () {
    $r = calc()->calculate(100_000, [
        1 => false, 2 => false, 3 => false, 4 => false, 5 => true,
    ]);

    expect($r['levels'][1]['paid'])->toBeTrue();   // auto level
    expect($r['levels'][3]['paid'])->toBeTrue();   // auto level
    expect($r['levels'][4]['paid'])->toBeFalse();
    expect($r['levels'][4]['pool_reason'])->toBe('inactive_upline');
    expect($r['levels'][5]['paid'])->toBeTrue();
    expect($r['levels'][6]['pool_reason'])->toBe('no_upline');
});

test('the invariant reconciles for awkward amounts', function (int $cents) {
    foreach ([[], [1 => true], [1 => true, 2 => false, 3 => true, 4 => false, 5 => true, 6 => false, 7 => true, 8 => false, 9 => true]] as $slots) {
        $r = calc()->calculate($cents, $slots);
        $paidLevels = collect($r['levels'])->where('paid', true)->sum('amount_cents');

        expect($r['seller_cents'] + $paidLevels + $r['pool_total_cents'])
            ->toBe($r['total_charge_cents']);
        expect($r['pool_rounding_cents'])->toBeGreaterThanOrEqual(0);
    }
})->with([1, 99, 9_999, 100_000, 123_457, 999_999_99]);

test('a one-cent sale produces all zeros and still reconciles', function () {
    $r = calc()->calculate(1, [1 => true]);

    expect($r['seller_cents'])->toBe(0);
    expect($r['total_charge_cents'])->toBe(0);
    expect($r['pool_total_cents'])->toBe(0);
});
