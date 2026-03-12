<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSeeText('Self-Hosted Email Marketing Platform')
        ->assertDontSeeText('Redirect in progress');
});

test('home route renders redirect screen when home redirect is configured', function () {
    config()->set('app.home_redirect', '/dashboard');

    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSeeText('Redirect in progress')
        ->assertSeeText('You will be redirected in 5 seconds to:')
        ->assertSee('/dashboard', false)
        ->assertSee('http-equiv="refresh"', false)
        ->assertSee('content="5;url=/dashboard"', false)
        ->assertSee(route('login'), false);
});

test('home route ignores unsafe home redirect values', function () {
    config()->set('app.home_redirect', 'javascript:alert(1)');

    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSeeText('Self-Hosted Email Marketing Platform')
        ->assertDontSeeText('Redirect in progress')
        ->assertDontSee('http-equiv="refresh"', false);
});
