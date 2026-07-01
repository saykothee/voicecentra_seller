<?php

use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-06-29'));
afterEach(fn () => Carbon::setTestNow());

function clientSale(User $seller, string $clientId, string $soldAt, bool $paid, ?string $paidAt = null, bool $trial = false, string $status = 'approved'): Sale
{
    return Sale::factory()->create([
        'seller_id' => $seller->id,
        'client_id' => $clientId,
        'sold_at' => $soldAt,
        'paid_at' => $paidAt,
        'paid' => $paid,
        'trial' => $trial,
        'status' => $status,
    ]);
}

test('guests and pending sellers cannot see client payments', function () {
    $this->get('/seller/client-payments')->assertRedirect(route('login'));
    $this->get('/admin/client-payments')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->pending()->create())
        ->get('/seller/client-payments')->assertRedirect(route('pending'));
});

test('a seller cannot open the admin client payments page', function () {
    $this->actingAs(User::factory()->approvedSeller()->create())
        ->get('/admin/client-payments')->assertForbidden();
});

test('admin sees every client across sellers; a seller sees only their own', function () {
    $s1 = User::factory()->approvedSeller()->create();
    $s2 = User::factory()->approvedSeller()->create();

    clientSale($s1, 'CLIENT-A', '2026-06-21', true, '2026-06-21');
    clientSale($s2, 'CLIENT-B', '2026-06-21', true, '2026-06-21');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/client-payments')->assertOk()
        ->assertSee('CLIENT-A')->assertSee('CLIENT-B');

    $this->actingAs($s1)
        ->get('/seller/client-payments')->assertOk()
        ->assertSee('CLIENT-A')->assertDontSee('CLIENT-B');
});

test('it flags late and to-be-paid clients and filters by status', function () {
    $seller = User::factory()->approvedSeller()->create();

    clientSale($seller, 'LATE-1', '2026-05-21', true, '2026-05-21'); // last paid May, due Jun 21 -> late
    clientSale($seller, 'OK-1', '2026-06-21', true, '2026-06-21');   // paid this cycle -> to_be_paid

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/client-payments')->assertOk()
        ->assertSee('LATE-1')->assertSee('OK-1')
        ->assertSee(__('messages.pay_status_late'))
        ->assertSee(__('messages.pay_status_to_be_paid'));

    $this->actingAs($admin)->get('/admin/client-payments?status=late')->assertOk()
        ->assertSee('LATE-1')->assertDontSee('OK-1');
});

test('a client on a free trial is flagged', function () {
    $seller = User::factory()->approvedSeller()->create();
    clientSale($seller, 'TRIAL-1', '2026-06-21', true, '2026-06-21', trial: true);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/client-payments')->assertOk()
        ->assertSee('TRIAL-1')
        ->assertSee(__('messages.trial_badge'));
});
