<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the locale can be switched to spanish and is stored in session', function () {
    $this->get('/lang/es')->assertRedirect();
    expect(session('locale'))->toBe('es');
});

test('an invalid locale is ignored', function () {
    $this->get('/lang/fr');
    expect(session('locale'))->toBeNull();
});

test('switching the locale queues a persistent cookie', function () {
    $this->get('/lang/es')->assertCookie('locale', 'es');
});

test('the locale cookie alone sets the language on a fresh session', function () {
    $this->withCookie('locale', 'es')
        ->get('/')
        ->assertSee(__('messages.hero_title', [], 'es'), false);
});

test('the language survives logout', function () {
    $user = User::factory()->approvedSeller()->create();

    $this->actingAs($user)->get('/lang/es')->assertCookie('locale', 'es');
    $this->actingAs($user)->post('/logout'); // invalidates the session

    $this->withCookie('locale', 'es')
        ->get('/')
        ->assertSee(__('messages.hero_title', [], 'es'), false);
});

test('an invalid locale cookie falls back to english', function () {
    $this->withCookie('locale', 'fr')
        ->get('/')
        ->assertSee(__('messages.hero_title', [], 'en'), false);
});
