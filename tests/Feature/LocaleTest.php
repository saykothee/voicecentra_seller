<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the locale can be switched to spanish and is stored in session', function () {
    $this->get('/lang/es')->assertRedirect();
    expect(session('locale'))->toBe('es');
});

test('an invalid locale is ignored', function () {
    $this->get('/lang/fr');
    expect(session('locale'))->toBeNull();
});
