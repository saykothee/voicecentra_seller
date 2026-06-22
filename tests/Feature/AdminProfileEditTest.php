<?php

use App\Models\User;
use Illuminate\Support\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the age accessor computes years from date_of_birth', function () {
    $user = User::factory()->approvedSeller()->create(['date_of_birth' => '1990-06-15']);

    expect($user->date_of_birth)->toBeInstanceOf(Carbon::class);
    expect($user->age)->toBe(Carbon::parse('1990-06-15')->age);
});

test('the age accessor is null when no date_of_birth is set', function () {
    $user = User::factory()->approvedSeller()->create(['date_of_birth' => null]);

    expect($user->age)->toBeNull();
});

test('registration requires date_of_birth and stores it', function () {
    $this->post('/register', [
        'name' => 'Dob Seller',
        'email' => 'dob@example.com',
        'phone' => '555-0100',
        'date_of_birth' => '1995-03-10',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'dob@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->date_of_birth->format('Y-m-d'))->toBe('1995-03-10');
});

test('registration rejects a missing date_of_birth', function () {
    $this->post('/register', [
        'name' => 'No Dob',
        'email' => 'nodob@example.com',
        'phone' => '555-0100',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('date_of_birth');

    expect(User::where('email', 'nodob@example.com')->exists())->toBeFalse();
});

test('registration rejects an under-18 date_of_birth', function () {
    $this->post('/register', [
        'name' => 'Too Young',
        'email' => 'young@example.com',
        'phone' => '555-0100',
        'date_of_birth' => now()->subYears(17)->toDateString(),
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('date_of_birth');

    expect(User::where('email', 'young@example.com')->exists())->toBeFalse();
});

test('admin can open the edit page for a seller', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create(['name' => 'Edit Me']);

    $this->actingAs($admin)->get(route('admin.sellers.edit', $seller))
        ->assertOk()
        ->assertSee('Edit Me')
        ->assertSee(__('messages.date_of_birth'));
});

test('admin update saves name, email, phone and date_of_birth', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($admin)->patch(route('admin.sellers.update', $seller), [
        'name' => 'New Name',
        'email' => 'new.email@example.com',
        'phone' => '555-9999',
        'date_of_birth' => '1988-02-20',
        'status' => $seller->status,
        'role' => 'seller',
    ])->assertRedirect(route('admin.sellers.index'));

    $seller->refresh();
    expect($seller->name)->toBe('New Name');
    expect($seller->email)->toBe('new.email@example.com');
    expect($seller->phone)->toBe('555-9999');
    expect($seller->date_of_birth->format('Y-m-d'))->toBe('1988-02-20');
});

test('admin can change role and approving via edit stamps the audit fields', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->pending()->create();

    $this->actingAs($admin)->patch(route('admin.sellers.update', $seller), [
        'name' => $seller->name,
        'email' => $seller->email,
        'phone' => $seller->phone,
        'date_of_birth' => null,
        'status' => 'approved',
        'role' => 'admin',
    ])->assertRedirect();

    $seller->refresh();
    expect($seller->role)->toBe('admin');
    expect($seller->status)->toBe('approved');
    expect($seller->approved_by)->toBe($admin->id);
    expect($seller->approved_at)->not->toBeNull();
});

test('moving a seller away from approved clears the audit fields', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create(['approved_by' => $admin->id, 'approved_at' => now()]);

    $this->actingAs($admin)->patch(route('admin.sellers.update', $seller), [
        'name' => $seller->name,
        'email' => $seller->email,
        'phone' => $seller->phone,
        'date_of_birth' => null,
        'status' => 'rejected',
        'role' => 'seller',
    ])->assertRedirect();

    $seller->refresh();
    expect($seller->status)->toBe('rejected');
    expect($seller->approved_at)->toBeNull();
    expect($seller->approved_by)->toBeNull();
});

test('re-approving an already-approved seller leaves the audit fields untouched', function () {
    $originalAdmin = User::factory()->admin()->create();
    $approvedAt = now()->subDays(5);
    $seller = User::factory()->approvedSeller()->create([
        'approved_by' => $originalAdmin->id,
        'approved_at' => $approvedAt,
    ]);

    $actingAdmin = User::factory()->admin()->create();
    $this->actingAs($actingAdmin)->patch(route('admin.sellers.update', $seller), [
        'name' => 'Still Approved',
        'email' => $seller->email,
        'phone' => $seller->phone,
        'date_of_birth' => null,
        'status' => 'approved',
        'role' => 'seller',
    ])->assertRedirect();

    $seller->refresh();
    expect($seller->name)->toBe('Still Approved');
    expect($seller->status)->toBe('approved');
    expect($seller->approved_by)->toBe($originalAdmin->id); // not re-stamped to the acting admin
    expect($seller->approved_at->toDateTimeString())->toBe($approvedAt->toDateTimeString());
});

test('email uniqueness ignores the edited user', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create(['email' => 'keep@example.com']);

    $this->actingAs($admin)->patch(route('admin.sellers.update', $seller), [
        'name' => $seller->name,
        'email' => 'keep@example.com',
        'phone' => $seller->phone,
        'date_of_birth' => null,
        'status' => $seller->status,
        'role' => 'seller',
    ])->assertRedirect();

    expect($seller->fresh()->email)->toBe('keep@example.com');
});

test('admin update rejects an under-18 date_of_birth', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($admin)->patch(route('admin.sellers.update', $seller), [
        'name' => $seller->name,
        'email' => $seller->email,
        'phone' => $seller->phone,
        'date_of_birth' => now()->subYears(10)->toDateString(),
        'status' => $seller->status,
        'role' => 'seller',
    ])->assertSessionHasErrors('date_of_birth');
});

test('an admin editing their own account cannot change their own role or status', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.sellers.update', $admin), [
        'name' => 'Renamed Admin',
        'email' => $admin->email,
        'phone' => $admin->phone,
        'date_of_birth' => null,
        'status' => 'rejected',
        'role' => 'seller',
    ])->assertRedirect();

    $admin->refresh();
    expect($admin->name)->toBe('Renamed Admin');
    expect($admin->role)->toBe('admin');
    expect($admin->status)->toBe('approved');
});

test('non-admins are forbidden from the edit and update routes', function () {
    $seller = User::factory()->approvedSeller()->create();
    $other = User::factory()->approvedSeller()->create();

    $this->actingAs($seller)->get(route('admin.sellers.edit', $other))->assertForbidden();
    $this->actingAs($seller)->patch(route('admin.sellers.update', $other), [
        'name' => 'x', 'email' => 'x@example.com', 'phone' => '1', 'status' => 'approved', 'role' => 'seller',
    ])->assertForbidden();
});
