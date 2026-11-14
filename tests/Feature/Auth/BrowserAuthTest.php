<?php

it('challenges requests at the browser auth gate without credentials', function () {
    config()->set('auth.browser_gate', [
        'username' => 'gate',
        'password' => 'secret',
    ]);

    $this->get(route('login'))
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate');
});

it('admits requests carrying valid browser auth gate credentials', function () {
    config()->set('auth.browser_gate', [
        'username' => 'gate',
        'password' => 'secret',
    ]);

    $this->get(route('login'), [
        'Authorization' => 'Basic '.base64_encode('gate:secret'),
    ])->assertOk();
});

it('passes through when the browser auth gate is not configured', function () {
    config()->set('auth.browser_gate', [
        'username' => null,
        'password' => null,
    ]);

    $this->get(route('login'))->assertOk();
});
