<?php

use App\Models\BonusPoolEntry;
use App\Models\CommissionPayout;
use App\Models\Sale;
use App\Models\User;
use App\Services\CommissionDistributor;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function distributor(): CommissionDistributor
{
    return app(CommissionDistributor::class);
}

function makeChain(int $people): array
{
    $users = [User::factory()->approvedSeller()->create()];
    for ($i = 1; $i < $people; $i++) {
        $users[] = User::factory()->approvedSeller()->withSponsor($users[$i - 1])->create();
    }

    return $users; // [0] is top-level; last is deepest
}

test('approving a sale writes seller payout, auto-level payouts and pool entries', function () {
    $admin = User::factory()->admin()->create();
    [$top, $mid, $seller] = makeChain(3); // seller has 2 uplines

    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000]);
    distributor()->distribute($sale, $admin);

    $sale->refresh();
    expect($sale->status)->toBe('approved');
    expect($sale->approved_by)->toBe($admin->id);

    $payouts = CommissionPayout::where('sale_id', $sale->id)->get();
    expect($payouts)->toHaveCount(3); // seller + L1 + L2

    expect($payouts->firstWhere('level', 0)->recipient_id)->toBe($seller->id);
    expect($payouts->firstWhere('level', 0)->amount_cents)->toBe(10_000);
    expect($payouts->firstWhere('level', 1)->recipient_id)->toBe($mid->id);
    expect($payouts->firstWhere('level', 1)->amount_cents)->toBe(5_000);
    expect($payouts->firstWhere('level', 2)->recipient_id)->toBe($top->id);

    // levels 3..9 have no upline -> pool, plus a rounding row
    $pool = BonusPoolEntry::where('sale_id', $sale->id)->get();
    expect($pool->where('reason', 'no_upline'))->toHaveCount(7);
    expect($pool->firstWhere('reason', 'no_upline')->level)->not->toBeNull();

    // invariant: everything reconciles to floor(100000*1023/5120) = 19980
    $total = $payouts->sum('amount_cents') + $pool->sum('amount_cents');
    expect($total)->toBe(19_980);
});

test('levels 4-9 only pay active uplines and the snapshot is stored', function () {
    $admin = User::factory()->admin()->create();
    $chain = makeChain(6); // seller at depth 6 has uplines L1..L5
    $seller = $chain[5];
    $l4 = $chain[1]; // level 4 upline
    $l5 = $chain[0]; // level 5 upline

    // Make ONLY the level-5 upline active (2 approved sales inside the window).
    Sale::factory()->approved()->count(2)->create([
        'seller_id' => $l5->id,
        'sold_at' => now()->subDays(10),
    ]);

    $sale = Sale::factory()->create([
        'seller_id' => $seller->id,
        'amount_cents' => 100_000,
        'sold_at' => now(),
    ]);
    distributor()->distribute($sale, $admin);

    $payouts = CommissionPayout::where('sale_id', $sale->id)->get();
    expect($payouts->firstWhere('level', 4))->toBeNull(); // inactive -> skipped
    expect($payouts->firstWhere('level', 5)->recipient_id)->toBe($l5->id);
    expect($payouts->firstWhere('level', 5)->recipient_was_active)->toBeTrue();

    $l4Entry = BonusPoolEntry::where('sale_id', $sale->id)->where('level', 4)->first();
    expect($l4Entry->reason)->toBe('inactive_upline');
    expect($l4Entry->amount_cents)->toBe(625);
});

test('the activity window is anchored at the sale sold_at, not at approval time', function () {
    $admin = User::factory()->admin()->create();
    $chain = makeChain(5);
    $seller = $chain[4];
    $l4 = $chain[0];

    // L4 upline has 2 approved sales ~100 days ago.
    Sale::factory()->approved()->count(2)->create([
        'seller_id' => $l4->id,
        'sold_at' => now()->subDays(100),
    ]);

    // A sale sold 95 days ago: window [185..95 days ago] includes those sales.
    $oldSale = Sale::factory()->create([
        'seller_id' => $seller->id, 'amount_cents' => 100_000, 'sold_at' => now()->subDays(95),
    ]);
    distributor()->distribute($oldSale, $admin);
    expect(CommissionPayout::where('sale_id', $oldSale->id)->where('level', 4)->exists())->toBeTrue();

    // A sale sold today: those sales are now outside the 90-day window.
    $newSale = Sale::factory()->create([
        'seller_id' => $seller->id, 'amount_cents' => 100_000, 'sold_at' => now(),
    ]);
    distributor()->distribute($newSale, $admin);
    expect(CommissionPayout::where('sale_id', $newSale->id)->where('level', 4)->exists())->toBeFalse();
});

test('refunding reverses payouts and offsets the pool', function () {
    $admin = User::factory()->admin()->create();
    [$top, $seller] = makeChain(2);

    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000]);
    distributor()->distribute($sale, $admin);

    $originalPool = (int) BonusPoolEntry::where('sale_id', $sale->id)->sum('amount_cents');
    distributor()->refund($sale->refresh());

    $sale->refresh();
    expect($sale->status)->toBe('refunded');
    expect(CommissionPayout::where('sale_id', $sale->id)->where('status', 'paid')->count())->toBe(0);

    expect((int) BonusPoolEntry::where('sale_id', $sale->id)->sum('amount_cents'))->toBe(0);
    expect(BonusPoolEntry::where('sale_id', $sale->id)->where('reason', 'refund_reversal')->first()->amount_cents)
        ->toBe(-$originalPool);
});

test('only pending sales can be distributed and only approved sales refunded', function () {
    $admin = User::factory()->admin()->create();
    $sale = Sale::factory()->approved()->create();

    expect(fn () => distributor()->distribute($sale, $admin))->toThrow(LogicException::class);
    expect(fn () => distributor()->refund(Sale::factory()->create()))->toThrow(LogicException::class);
});

test('auto levels 1-3 pay an inactive upline but snapshot it as inactive', function () {
    $admin = User::factory()->admin()->create();
    [$sponsor, $seller] = makeChain(2); // sponsor has zero sales -> inactive

    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000]);
    distributor()->distribute($sale, $admin);

    $l1 = CommissionPayout::where('sale_id', $sale->id)->where('level', 1)->first();
    expect($l1->recipient_id)->toBe($sponsor->id);
    expect($l1->status)->toBe('paid');                 // auto level pays anyway
    expect($l1->recipient_was_active)->toBeFalse();    // but the snapshot is honest
});

test('a stale model cannot distribute a sale already processed by another request', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000]);

    $stale = Sale::find($sale->id);          // second in-memory instance, still 'pending'
    distributor()->distribute($sale, $admin); // first request wins

    expect(fn () => distributor()->distribute($stale, $admin))->toThrow(LogicException::class);
    expect(CommissionPayout::where('sale_id', $sale->id)->where('level', 0)->count())->toBe(1);
});

test('a full nine-level chain pays every upline with no no_upline pool entries', function () {
    $admin = User::factory()->admin()->create();
    $chain = makeChain(10); // depth 1..10; seller at depth 10 has exactly 9 uplines
    $seller = $chain[9];

    // make ALL uplines active so levels 4-9 pay
    foreach (range(0, 8) as $i) {
        Sale::factory()->approved()->count(2)->create([
            'seller_id' => $chain[$i]->id,
            'sold_at' => now()->subDays(10),
        ]);
    }

    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000, 'sold_at' => now()]);
    distributor()->distribute($sale, $admin);

    $payouts = CommissionPayout::where('sale_id', $sale->id)->get();
    expect($payouts)->toHaveCount(10); // seller + 9 uplines
    expect($payouts->firstWhere('level', 9)->amount_cents)->toBe(19);
    expect(BonusPoolEntry::where('sale_id', $sale->id)->where('reason', 'no_upline')->count())->toBe(0);
    // only the rounding remainder goes to the pool
    expect((int) BonusPoolEntry::where('sale_id', $sale->id)->sum('amount_cents'))->toBe(1);
});

test('a stale model cannot refund a sale already refunded by another request', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000]);
    distributor()->distribute($sale, $admin);

    $live = $sale->fresh();
    $stale = Sale::find($sale->id);
    distributor()->refund($live);

    expect(fn () => distributor()->refund($stale))->toThrow(LogicException::class);
    expect(\App\Models\BonusPoolEntry::where('sale_id', $sale->id)->where('reason', 'refund_reversal')->count())->toBe(1);
});
