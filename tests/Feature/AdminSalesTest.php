<?php

use App\Models\CommissionPayout;
use App\Models\Sale;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin can list sales and filter by status', function () {
    $admin = User::factory()->admin()->create();
    Sale::factory()->create(['notes' => 'pending-note']);
    Sale::factory()->approved()->create(['notes' => 'approved-note']);

    $this->actingAs($admin)->get('/admin/sales')->assertOk()
        ->assertSee('pending-note')->assertSee('approved-note');

    $this->actingAs($admin)->get('/admin/sales?status=pending')->assertOk()
        ->assertSee('pending-note')->assertDontSee('approved-note');
});

test('admin approval distributes commissions', function () {
    $admin = User::factory()->admin()->create();
    $sale = Sale::factory()->create(['amount_cents' => 100_000]);

    $this->actingAs($admin)->patch(route('admin.sales.approve', $sale))->assertRedirect();

    expect($sale->fresh()->status)->toBe('approved');
    expect(CommissionPayout::where('sale_id', $sale->id)->where('level', 0)->first()->amount_cents)->toBe(10_000);
});

test('admin can reject a pending sale without distribution', function () {
    $admin = User::factory()->admin()->create();
    $sale = Sale::factory()->create();

    $this->actingAs($admin)->patch(route('admin.sales.reject', $sale))->assertRedirect();

    expect($sale->fresh()->status)->toBe('rejected');
    expect(CommissionPayout::count())->toBe(0);
});

test('admin can refund an approved sale', function () {
    $admin = User::factory()->admin()->create();
    $sale = Sale::factory()->create(['amount_cents' => 100_000]);
    $this->actingAs($admin)->patch(route('admin.sales.approve', $sale));

    $this->actingAs($admin)->patch(route('admin.sales.refund', $sale))->assertRedirect();

    expect($sale->fresh()->status)->toBe('refunded');
    expect(CommissionPayout::where('sale_id', $sale->id)->where('status', 'paid')->count())->toBe(0);
});

test('approving a non-pending sale returns 404 and refunding a non-approved sale returns 404', function () {
    $admin = User::factory()->admin()->create();
    $approved = Sale::factory()->approved()->create();
    $pending = Sale::factory()->create();

    $this->actingAs($admin)->patch(route('admin.sales.approve', $approved))->assertNotFound();
    $this->actingAs($admin)->patch(route('admin.sales.refund', $pending))->assertNotFound();
});

test('non-admins cannot manage sales', function () {
    $seller = User::factory()->approvedSeller()->create();
    $sale = Sale::factory()->create();

    $this->actingAs($seller)->get('/admin/sales')->assertForbidden();
    $this->actingAs($seller)->get(route('admin.sales.create'))->assertForbidden();
    $this->actingAs($seller)->post(route('admin.sales.store'), [])->assertForbidden();
    $this->actingAs($seller)->patch(route('admin.sales.approve', $sale))->assertForbidden();
    $this->actingAs($seller)->patch(route('admin.sales.reject', $sale))->assertForbidden();
    $this->actingAs($seller)->patch(route('admin.sales.refund', $sale))->assertForbidden();
});

test('admin can view the create sale form', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create(['name' => 'Jane Seller']);

    $this->actingAs($admin)->get(route('admin.sales.create'))->assertOk()
        ->assertSee('Jane Seller');
});

test('admin can register a pending sale for a seller', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($admin)->post(route('admin.sales.store'), [
        'seller_id' => $seller->id,
        'client_id' => 'CLIENT-123',
        'amount' => '250.50',
        'sold_at' => now()->subDay()->toDateString(),
        'paid_at' => now()->subDay()->toDateString(),
        'paid' => '1',
        'trial' => '1',
        'status' => 'pending',
        'notes' => 'manual entry',
    ])->assertRedirect(route('admin.sales.index'));

    $sale = Sale::where('seller_id', $seller->id)->first();
    expect($sale)->not->toBeNull();
    expect($sale->client_id)->toBe('CLIENT-123');
    expect($sale->amount_cents)->toBe(25_050);
    expect($sale->status)->toBe('pending');
    expect($sale->paid)->toBeTrue();
    expect($sale->trial)->toBeTrue();
    expect($sale->notes)->toBe('manual entry');
    expect(CommissionPayout::count())->toBe(0);
});

test('admin can register an approved sale which distributes commissions', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($admin)->post(route('admin.sales.store'), [
        'seller_id' => $seller->id,
        'client_id' => 'EXT-9',
        'amount' => '1000',
        'sold_at' => now()->toDateString(),
        'status' => 'approved',
    ])->assertRedirect(route('admin.sales.index'));

    $sale = Sale::where('seller_id', $seller->id)->first();
    expect($sale->status)->toBe('approved');
    expect($sale->approved_by)->toBe($admin->id);
    expect(CommissionPayout::where('sale_id', $sale->id)->where('level', 0)->first()->amount_cents)->toBe(10_000);
});

test('registering a sale requires a valid seller, client id and amount', function () {
    $admin = User::factory()->admin()->create();
    $nonSeller = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.sales.store'), [
        'seller_id' => $nonSeller->id,
        'amount' => '0',
        'sold_at' => now()->addWeek()->toDateString(),
        'status' => 'pending',
    ])->assertSessionHasErrors(['seller_id', 'client_id', 'amount', 'sold_at']);

    expect(Sale::count())->toBe(0);
});
