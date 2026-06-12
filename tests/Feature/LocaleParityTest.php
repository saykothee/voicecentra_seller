<?php

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
