<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user role and status helpers report correctly', function () {
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->pending()->create();
    $approved = User::factory()->approvedSeller()->create();

    expect($admin->isAdmin())->toBeTrue();
    expect($admin->isSeller())->toBeFalse();

    expect($pending->isSeller())->toBeTrue();
    expect($pending->isApproved())->toBeFalse();

    expect($approved->isApproved())->toBeTrue();
});

test('new registrations become pending sellers', function () {
    $response = $this->post('/register', [
        'name' => 'Jane Seller',
        'email' => 'jane@example.com',
        'phone' => '555-0100',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe('seller');
    expect($user->status)->toBe('pending');
    expect($user->phone)->toBe('555-0100');
    $this->assertAuthenticated();
});

test('dashboard redirects admins to the admin dashboard', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/dashboard')->assertRedirect('/admin/dashboard');
});

test('dashboard redirects approved sellers to the seller dashboard', function () {
    $seller = User::factory()->approvedSeller()->create();
    $this->actingAs($seller)->get('/dashboard')->assertRedirect('/seller/dashboard');
});

test('dashboard redirects pending sellers to the pending page', function () {
    $seller = User::factory()->pending()->create();
    $this->actingAs($seller)->get('/dashboard')->assertRedirect(route('pending'));
});

test('the pending page renders for an authenticated seller', function () {
    $seller = User::factory()->pending()->create();
    $this->actingAs($seller)->get('/pending')->assertOk();
});

test('approved sellers can view their dashboard', function () {
    $seller = User::factory()->approvedSeller()->create();
    $this->actingAs($seller)->get('/seller/dashboard')->assertOk()->assertSee($seller->name);
});

test('pending sellers cannot view the seller dashboard', function () {
    $seller = User::factory()->pending()->create();
    $this->actingAs($seller)->get('/seller/dashboard')->assertRedirect(route('pending'));
});

test('admins can view the admin dashboard and sellers list', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    $this->actingAs($admin)->get('/admin/sellers')->assertOk();
});

test('non-admins are forbidden from admin routes', function () {
    $seller = User::factory()->approvedSeller()->create();
    $this->actingAs($seller)->get('/admin/dashboard')->assertForbidden();
    $this->actingAs($seller)->get('/admin/sellers')->assertForbidden();
});

test('an admin can approve a pending seller', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->pending()->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.approve', $seller))
        ->assertRedirect();

    $seller->refresh();
    expect($seller->status)->toBe('approved');
    expect($seller->approved_by)->toBe($admin->id);
    expect($seller->approved_at)->not->toBeNull();
});

test('an admin can reject a pending seller', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->pending()->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.reject', $seller))
        ->assertRedirect();

    $seller->refresh();
    expect($seller->status)->toBe('rejected');
    expect($seller->approved_at)->toBeNull();
    expect($seller->approved_by)->toBeNull();
});

test('a seller can update their phone via the profile form', function () {
    $seller = User::factory()->approvedSeller()->create(['phone' => '555-0000']);

    $this->actingAs($seller)->patch('/profile', [
        'name' => $seller->name,
        'email' => $seller->email,
        'phone' => '555-9999',
    ])->assertRedirect();

    expect($seller->fresh()->phone)->toBe('555-9999');
});

test('rejected sellers cannot view the seller dashboard', function () {
    $seller = User::factory()->create(['role' => 'seller', 'status' => 'rejected']);
    $this->actingAs($seller)->get('/seller/dashboard')->assertRedirect(route('pending'));
});

test('admins are redirected away from the seller dashboard', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/seller/dashboard')->assertRedirect(route('dashboard'));
});

test('approved sellers and admins are redirected away from the pending page', function () {
    $seller = User::factory()->approvedSeller()->create();
    $this->actingAs($seller)->get('/pending')->assertRedirect(route('seller.dashboard'));

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/pending')->assertRedirect(route('admin.dashboard'));
});

test('non-admins cannot approve or reject sellers', function () {
    $seller = User::factory()->approvedSeller()->create();
    $target = User::factory()->pending()->create();
    $this->actingAs($seller)->patch(route('admin.sellers.approve', $target))->assertForbidden();
    $this->actingAs($seller)->patch(route('admin.sellers.reject', $target))->assertForbidden();
});

test('approving a non-seller target returns 404', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($admin)->patch(route('admin.sellers.approve', $otherAdmin))->assertNotFound();
});

test('the profile form rejects an empty phone and keeps the existing value', function () {
    $seller = User::factory()->approvedSeller()->create(['phone' => '555-0000']);
    $this->actingAs($seller)->from('/profile')->patch('/profile', [
        'name' => $seller->name,
        'email' => $seller->email,
        'phone' => '',
    ])->assertSessionHasErrors('phone');
    expect($seller->fresh()->phone)->toBe('555-0000');
});
