<?php

use App\Models\ExternalSale;
use App\Models\User;
use Firebase\JWT\JWT;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// HS256 requires >= 256-bit (32-byte) keys (firebase/php-jwt v7).
const TEST_SECRET = 'test-secret-32-bytes-padded-1234';

function validToken(array $claims = []): string
{
    config(['external_sales.jwt_secret' => TEST_SECRET]);

    return JWT::encode(
        array_merge(['iss' => 'external', 'iat' => time(), 'exp' => time() + 3600], $claims),
        TEST_SECRET,
        'HS256'
    );
}

function validPayload(int $sellerId): array
{
    return [
        'seller_id' => $sellerId,
        'sale_date' => '2026-06-20',
        'paid_at' => '2026-06-21T14:30:00Z',
        'amount' => 49.99,
        'paid' => true,
        'free_trial' => false,
    ];
}

test('a request without a token is rejected with 401', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->postJson('/api/external-sales', validPayload($seller->id))
        ->assertStatus(401);
});

test('a request with a malformed token is rejected with 401', function () {
    config(['external_sales.jwt_secret' => TEST_SECRET]);
    $seller = User::factory()->approvedSeller()->create();

    $this->withToken('not-a-jwt')
        ->postJson('/api/external-sales', validPayload($seller->id))
        ->assertStatus(401);
});

test('a token signed with the wrong secret is rejected with 401', function () {
    config(['external_sales.jwt_secret' => TEST_SECRET]);
    $seller = User::factory()->approvedSeller()->create();
    $bad = JWT::encode(['exp' => time() + 3600], 'WRONG-secret-32-bytes-padded-123', 'HS256');

    $this->withToken($bad)
        ->postJson('/api/external-sales', validPayload($seller->id))
        ->assertStatus(401);
});

test('an expired token is rejected with 401', function () {
    $seller = User::factory()->approvedSeller()->create();
    $expired = validToken(['exp' => time() - 10]);

    $this->withToken($expired)
        ->postJson('/api/external-sales', validPayload($seller->id))
        ->assertStatus(401);
});

test('an external sale persists with cents and boolean casts', function () {
    $seller = User::factory()->approvedSeller()->create();

    $sale = ExternalSale::create([
        'seller_id' => $seller->id,
        'sale_date' => '2026-06-20',
        'paid_at' => '2026-06-21 14:30:00',
        'amount_cents' => 4999,
        'paid' => true,
        'free_trial' => false,
    ]);

    $fresh = $sale->fresh();
    expect($fresh->amount_cents)->toBe(4999);
    expect($fresh->paid)->toBeTrue();
    expect($fresh->free_trial)->toBeFalse();
    expect($fresh->sale_date->format('Y-m-d'))->toBe('2026-06-20');
    expect($fresh->seller->id)->toBe($seller->id);
});
