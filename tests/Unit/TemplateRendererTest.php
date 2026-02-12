<?php

use App\Support\TemplateRenderer;

it('replaces placeholder variables in html and subject', function () {
    $renderer = new TemplateRenderer;

    $result = $renderer->render(
        '<h1>Hello {{ first_name }} {{ last_name }}</h1><p>{{ company }}</p>',
        [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'company' => 'Acme',
        ],
    );

    expect($result)->toBe('<h1>Hello Jane Doe</h1><p>Acme</p>');
});

it('keeps unknown placeholders unchanged', function () {
    $renderer = new TemplateRenderer;

    $result = $renderer->render('<p>{{ unknown_key }}</p>', []);

    expect($result)->toBe('<p>{{ unknown_key }}</p>');
});
