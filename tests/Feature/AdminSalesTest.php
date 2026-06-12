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
    $this->actingAs($seller)->patch(route('admin.sales.approve', $sale))->assertForbidden();
    $this->actingAs($seller)->patch(route('admin.sales.reject', $sale))->assertForbidden();
    $this->actingAs($seller)->patch(route('admin.sales.refund', $sale))->assertForbidden();
});
