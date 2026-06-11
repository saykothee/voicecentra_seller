<?php

test('the landing page renders with the hero headline and CTAs', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('messages.hero_title'))
        ->assertSee(__('messages.become_seller'))
        ->assertSee('VoiceCentra');
});

test('the landing page renders in spanish when the locale is set', function () {
    session(['locale' => 'es']);
    $this->get('/')
        ->assertOk()
        ->assertSee(__('messages.hero_title', [], 'es'), false);
});
