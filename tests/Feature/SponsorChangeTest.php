<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin can change a seller sponsor', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $newSponsor = User::factory()->approvedSeller()->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => $newSponsor->email])
        ->assertRedirect();

    $seller->refresh();
    expect($seller->parent_id)->toBe($newSponsor->id);
    expect($seller->depth)->toBe(2);
});

test('admin can make a seller top-level', function () {
    $admin = User::factory()->admin()->create();
    $sponsor = User::factory()->approvedSeller()->create();
    $seller = User::factory()->approvedSeller()->withSponsor($sponsor)->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => ''])
        ->assertRedirect();

    $seller->refresh();
    expect($seller->parent_id)->toBeNull();
    expect($seller->depth)->toBe(1);
});

test('cycles are rejected', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $child = User::factory()->approvedSeller()->withSponsor($seller)->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => $child->email])
        ->assertSessionHasErrors('sponsor_email');

    expect($seller->fresh()->parent_id)->toBeNull();
});

test('moves that would exceed depth 10 are rejected', function () {
    $admin = User::factory()->admin()->create();
    $users = [User::factory()->approvedSeller()->create()];
    for ($i = 1; $i < 9; $i++) {
        $users[] = User::factory()->approvedSeller()->withSponsor($users[$i - 1])->create();
    }
    // users[8] is at depth 9; a seller with a child (height 2) cannot move under it
    $seller = User::factory()->approvedSeller()->create();
    User::factory()->approvedSeller()->withSponsor($seller)->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => $users[8]->email])
        ->assertSessionHasErrors('sponsor_email');
});

test('an unknown or non-approved sponsor email is rejected', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $pending = User::factory()->pending()->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => 'ghost@nowhere.com'])
        ->assertSessionHasErrors('sponsor_email');

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => $pending->email])
        ->assertSessionHasErrors('sponsor_email');
});

test('the sponsor form lists only eligible sponsors in the dropdown', function () {
    $admin = User::factory()->admin()->create();

    $seller = User::factory()->approvedSeller()->create(['name' => 'Edited Seller']);
    User::factory()->approvedSeller()->withSponsor($seller)->create(['name' => 'Descendant Seller']);
    User::factory()->approvedSeller()->create(['name' => 'Eligible Sponsor']);
    User::factory()->pending()->create(['name' => 'Pending Person']);

    // A depth-9 candidate: the edited seller has height 2 (itself + descendant), 9 + 2 > 10.
    $chain = [User::factory()->approvedSeller()->create(['name' => 'Deep Root'])];
    for ($i = 1; $i < 9; $i++) {
        $chain[] = User::factory()->approvedSeller()->withSponsor($chain[$i - 1])->create(['name' => 'Deep '.$i]);
    }

    $response = $this->actingAs($admin)
        ->get(route('admin.sellers.sponsor.edit', $seller))
        ->assertOk();

    $response->assertSee('Eligible Sponsor');
    $response->assertSee(__('messages.none_top_level'));
    $response->assertSee('Deep 7');                // depth 8: 8 + 2 = 10, still eligible
    $response->assertDontSee('Descendant Seller'); // own subtree (covers self-exclusion path)
    $response->assertDontSee('Pending Person');    // not approved
    $response->assertDontSee('Deep 8');            // depth 9: 9 + 2 > 10
});
