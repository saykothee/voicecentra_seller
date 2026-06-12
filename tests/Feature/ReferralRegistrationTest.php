<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Seller',
        'email' => 'new@example.com',
        'phone' => '555-0100',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}

test('registering through a referral link sets sponsor and depth', function () {
    $sponsor = User::factory()->approvedSeller()->create();

    $this->post('/register', registrationPayload(['ref' => $sponsor->referral_code]));

    $user = User::where('email', 'new@example.com')->first();
    expect($user->parent_id)->toBe($sponsor->id);
    expect($user->depth)->toBe($sponsor->depth + 1);
});

test('registering without a ref creates a top-level seller', function () {
    $this->post('/register', registrationPayload());

    $user = User::where('email', 'new@example.com')->first();
    expect($user->parent_id)->toBeNull();
    expect($user->depth)->toBe(1);
    expect($user->referral_code)->toHaveLength(8);
});

test('an invalid referral code is rejected', function () {
    $this->post('/register', registrationPayload(['ref' => 'NOPENOPE']))
        ->assertSessionHasErrors('ref');

    expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
});

test('a non-approved sponsor code is rejected', function () {
    $pending = User::factory()->pending()->create();

    $this->post('/register', registrationPayload(['ref' => $pending->referral_code]))
        ->assertSessionHasErrors('ref');
});

test('a full chain (depth 10) rejects new signups', function () {
    $users = [User::factory()->approvedSeller()->create()];
    for ($i = 1; $i < 10; $i++) {
        $users[] = User::factory()->approvedSeller()->withSponsor($users[$i - 1])->create();
    }
    expect($users[9]->depth)->toBe(10);

    $this->post('/register', registrationPayload(['ref' => $users[9]->referral_code]))
        ->assertSessionHasErrors('ref');
});

test('the register page shows the sponsor banner for a valid ref', function () {
    $sponsor = User::factory()->approvedSeller()->create(['name' => 'Sponsor Name']);

    $this->get('/register?ref='.$sponsor->referral_code)
        ->assertOk()
        ->assertSee('Sponsor Name');
});

test('a signup under a depth-9 sponsor lands exactly at depth 10', function () {
    $users = [User::factory()->approvedSeller()->create()];
    for ($i = 1; $i < 9; $i++) {
        $users[] = User::factory()->approvedSeller()->withSponsor($users[$i - 1])->create();
    }
    expect($users[8]->depth)->toBe(9);

    $this->post('/register', registrationPayload(['ref' => $users[8]->referral_code]));

    $user = User::where('email', 'new@example.com')->first();
    expect($user->depth)->toBe(10);
});
