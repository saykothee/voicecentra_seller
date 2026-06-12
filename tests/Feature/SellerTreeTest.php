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

test('subtree returns a nested node with descendant counts', function () {
    $tree = app(\App\Services\SellerTree::class);

    $root = User::factory()->approvedSeller()->create();
    $a = User::factory()->approvedSeller()->withSponsor($root)->create();
    $b = User::factory()->approvedSeller()->withSponsor($root)->create();
    $c = User::factory()->approvedSeller()->withSponsor($a)->create();
    User::factory()->approvedSeller()->create(); // unrelated seller

    $node = $tree->subtree($root);

    expect($node['user']->id)->toBe($root->id);
    expect($node['descendants_count'])->toBe(3);
    expect(collect($node['children'])->pluck('user.id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

test('forest returns all top-level sellers with their subtrees', function () {
    $tree = app(\App\Services\SellerTree::class);

    $r1 = User::factory()->approvedSeller()->create();
    $r2 = User::factory()->pending()->create();
    User::factory()->approvedSeller()->withSponsor($r1)->create();
    User::factory()->admin()->create(); // admins are not part of the network

    $forest = $tree->forest();

    expect(collect($forest)->pluck('user.id')->sort()->values()->all())
        ->toBe(collect([$r1->id, $r2->id])->sort()->values()->all());
});

test('isInSubtree and subtreeHeight report correctly', function () {
    $tree = app(\App\Services\SellerTree::class);

    $root = User::factory()->approvedSeller()->create();
    $mid = User::factory()->approvedSeller()->withSponsor($root)->create();
    $leaf = User::factory()->approvedSeller()->withSponsor($mid)->create();
    $other = User::factory()->approvedSeller()->create();

    expect($tree->isInSubtree($leaf, $root))->toBeTrue();
    expect($tree->isInSubtree($root, $leaf))->toBeFalse();
    expect($tree->isInSubtree($other, $root))->toBeFalse();
    expect($tree->subtreeHeight($root))->toBe(3);
    expect($tree->subtreeHeight($leaf))->toBe(1);
});

test('changeSponsor moves a subtree and recaches depths', function () {
    $tree = app(\App\Services\SellerTree::class);

    $oldRoot = User::factory()->approvedSeller()->create();
    $mover = User::factory()->approvedSeller()->withSponsor($oldRoot)->create();
    $grandchild = User::factory()->approvedSeller()->withSponsor($mover)->create();
    $newRoot = User::factory()->approvedSeller()->create();
    $newParent = User::factory()->approvedSeller()->withSponsor($newRoot)->create(); // depth 2

    $tree->changeSponsor($mover, $newParent);

    $mover->refresh();
    $grandchild->refresh();
    expect($mover->parent_id)->toBe($newParent->id);
    expect($mover->depth)->toBe(3);
    expect($grandchild->depth)->toBe(4);

    $tree->changeSponsor($mover, null);
    expect($mover->fresh()->depth)->toBe(1);
    expect($grandchild->fresh()->depth)->toBe(2);
});

test('recentSalesCounts groups approved sales inside the window', function () {
    $tree = app(\App\Services\SellerTree::class);

    $seller = User::factory()->approvedSeller()->create();
    Sale::factory()->approved()->count(2)->create(['seller_id' => $seller->id, 'sold_at' => now()->subDays(5)]);
    Sale::factory()->approved()->create(['seller_id' => $seller->id, 'sold_at' => now()->subDays(120)]); // outside
    Sale::factory()->create(['seller_id' => $seller->id, 'sold_at' => now()->subDays(2)]); // pending

    $counts = $tree->recentSalesCounts(collect([$seller]));

    expect($counts[$seller->id])->toBe(2);
});
