<?php

use App\Models\Sale;
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

test('a token without an exp claim is rejected with 401', function () {
    config(['external_sales.jwt_secret' => TEST_SECRET]);
    $seller = User::factory()->approvedSeller()->create();
    $noExp = JWT::encode(['iss' => 'external', 'iat' => time()], TEST_SECRET, 'HS256');

    $this->withToken($noExp)
        ->postJson('/api/external-sales', validPayload($seller->id))
        ->assertStatus(401);
});

test('a seller_id that is not a seller (e.g. an admin) returns 422', function () {
    $admin = User::factory()->admin()->create();

    $this->withToken(validToken())
        ->postJson('/api/external-sales', validPayload($admin->id))
        ->assertStatus(422)->assertJsonValidationErrors('seller_id');
});

test('a valid request records an approved sale and returns 201', function () {
    $seller = User::factory()->approvedSeller()->create();

    $response = $this->withToken(validToken())
        ->postJson('/api/external-sales', validPayload($seller->id));

    $response->assertStatus(201)->assertJson(['status' => 'recorded']);

    $sale = Sale::first();
    expect($sale->seller_id)->toBe($seller->id);
    expect($sale->amount_cents)->toBe(4999);
    expect($sale->status)->toBe('approved');
    expect($sale->approved_at)->not->toBeNull();
    expect($sale->paid)->toBeTrue();
    expect($sale->trial)->toBeFalse();
    expect($sale->sold_at->format('Y-m-d'))->toBe('2026-06-20');
    expect($response->json('id'))->toBe($sale->id);
});

test('the incoming free_trial flag maps to the trial column', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->withToken(validToken())
        ->postJson('/api/external-sales', [...validPayload($seller->id), 'free_trial' => true])
        ->assertStatus(201);

    expect(Sale::first()->trial)->toBeTrue();
});

test('a whole-number amount converts to cents correctly', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->withToken(validToken())
        ->postJson('/api/external-sales', [...validPayload($seller->id), 'amount' => 100])
        ->assertStatus(201);

    expect(Sale::first()->amount_cents)->toBe(10000);
});

test('an unpaid sale with null paid_at is accepted', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->withToken(validToken())
        ->postJson('/api/external-sales', [
            ...validPayload($seller->id), 'paid' => false, 'paid_at' => null,
        ])
        ->assertStatus(201);

    $sale = Sale::first();
    expect($sale->paid)->toBeFalse();
    expect($sale->paid_at)->toBeNull();
});

test('a missing required field returns 422', function () {
    $seller = User::factory()->approvedSeller()->create();
    $payload = validPayload($seller->id);
    unset($payload['amount']);

    $this->withToken(validToken())
        ->postJson('/api/external-sales', $payload)
        ->assertStatus(422)->assertJsonValidationErrors('amount');
});

test('an unknown seller_id returns 422', function () {
    $this->withToken(validToken())
        ->postJson('/api/external-sales', validPayload(999999))
        ->assertStatus(422)->assertJsonValidationErrors('seller_id');
});

test('a negative amount returns 422', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->withToken(validToken())
        ->postJson('/api/external-sales', [...validPayload($seller->id), 'amount' => -5])
        ->assertStatus(422)->assertJsonValidationErrors('amount');
});
