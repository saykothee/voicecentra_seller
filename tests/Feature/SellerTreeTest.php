<?php

use App\Models\Sale;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('users get a unique referral code on creation', function () {
    $a = User::factory()->approvedSeller()->create();
    $b = User::factory()->approvedSeller()->create();

    expect($a->referral_code)->toHaveLength(8);
    expect($b->referral_code)->not->toBe($a->referral_code);
});

test('parent and children relations work with cached depth', function () {
    $root = User::factory()->approvedSeller()->create();
    $child = User::factory()->approvedSeller()->withSponsor($root)->create();

    expect($child->parent->id)->toBe($root->id);
    expect($root->children->pluck('id')->all())->toBe([$child->id]);
    expect($root->depth)->toBe(1);
    expect($child->depth)->toBe(2);
});

test('upline chain walks at most nine levels', function () {
    $users = [User::factory()->approvedSeller()->create()];
    for ($i = 1; $i <= 10; $i++) {
        $users[$i] = User::factory()->approvedSeller()->withSponsor($users[$i - 1])->create();
    }
    // users[10] has 10 ancestors but the chain caps at 9
    $chain = $users[10]->uplineChain();

    expect($chain)->toHaveCount(9);
    expect($chain[1]->id)->toBe($users[9]->id);
    expect($chain[9]->id)->toBe($users[1]->id);
});

test('a sale can be created with cents and casts sold_at', function () {
    $sale = Sale::factory()->create(['amount_cents' => 123456]);

    expect($sale->amount_cents)->toBe(123456);
    expect($sale->sold_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($sale->status)->toBe('pending');
});
