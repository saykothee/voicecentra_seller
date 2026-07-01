<?php

use App\Services\RampSchedule;
use Tests\TestCase;

uses(TestCase::class);

test('it builds the 6-month ramp from the tenure thresholds', function () {
    $s = (new RampSchedule())->forMonths(6);

    expect($s)->toHaveCount(6);
    expect($s[1])->toBe(['seller' => 'none', 'chain' => 'none']);
    expect($s[2])->toBe(['seller' => 'flat', 'chain' => 'full']);
    expect($s[4])->toBe(['seller' => 'flat', 'chain' => 'full']);
    expect($s[5])->toBe(['seller' => 'full', 'chain' => 'full']);
    expect($s[6])->toBe(['seller' => 'full', 'chain' => 'full']);
});

test('it extends the ramp to any length, staying full from month 5 on', function () {
    $s = (new RampSchedule())->forMonths(60);

    expect($s)->toHaveCount(60);
    expect($s[1]['seller'])->toBe('none');
    foreach (range(2, 4) as $m) {
        expect($s[$m])->toBe(['seller' => 'flat', 'chain' => 'full']);
    }
    foreach (range(5, 60) as $m) {
        expect($s[$m])->toBe(['seller' => 'full', 'chain' => 'full']);
    }
});

test('it exposes the configured month bounds', function () {
    $r = new RampSchedule();

    expect($r->minMonths())->toBe(6);
    expect($r->maxMonths())->toBe(60);
    expect($r->defaultMonths())->toBe(6);
});
