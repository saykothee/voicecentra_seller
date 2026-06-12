<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('en and es message catalogs have identical keys', function () {
    $en = require lang_path('en/messages.php');
    $es = require lang_path('es/messages.php');

    expect(array_keys(array_diff_key($en, $es)))->toBe([]);
    expect(array_keys(array_diff_key($es, $en)))->toBe([]);
});

test('commission keys exist', function () {
    expect(__('messages.my_network'))->not->toBe('messages.my_network');
    expect(__('messages.calculator'))->not->toBe('messages.calculator');
    expect(__('messages.bonus_pool'))->not->toBe('messages.bonus_pool');
});

test('the calculator renders in spanish when the locale is set', function () {
    $seller = \App\Models\User::factory()->approvedSeller()->create();
    session(['locale' => 'es']);

    $this->actingAs($seller)->get('/calculator')
        ->assertOk()
        ->assertSee(__('messages.calculator_title', [], 'es'), false); // "Calculadora de Comisiones"
});
