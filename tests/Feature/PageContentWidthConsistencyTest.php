<?php

use App\Models\User;

it('uses a consistent content width across primary app pages', function () {
    $this->actingAs(User::factory()->create());

    $routes = [
        route('dashboard'),
        route('contacts.index'),
        route('broadcasts.index'),
        route('broadcasts.history'),
        route('templates.index'),
    ];

    foreach ($routes as $route) {
        $this->get($route)
            ->assertSuccessful()
            ->assertSee('max-w-6xl', escape: false)
            ->assertDontSee('max-w-7xl', escape: false);
    }
});
