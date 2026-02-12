<?php

use App\Support\EmailTemplateBuilderRenderer;

it('renders row column element schema into email safe html', function () {
    $html = app(EmailTemplateBuilderRenderer::class)->render([
        'schema_version' => 2,
        'theme' => [],
        'rows' => [
            [
                'id' => 'row-1',
                'columns' => [
                    [
                        'id' => 'col-1',
                        'width' => '100%',
                        'elements' => [
                            [
                                'id' => 'el-1',
                                'type' => 'text',
                                'content' => ['text' => 'Hello {{ first_name }}'],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                            [
                                'id' => 'el-2',
                                'type' => 'button',
                                'content' => ['text' => 'Open', 'url' => 'https://example.com'],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                        ],
                    ],
                ],
                'style' => [],
            ],
        ],
    ]);

    expect($html)->toContain('Hello {{ first_name }}');
    expect($html)->toContain('https://example.com');
    expect($html)->toContain('<table role="presentation"');
});

it('escapes unsafe text content', function () {
    $html = app(EmailTemplateBuilderRenderer::class)->render([
        'schema_version' => 2,
        'theme' => [],
        'rows' => [
            [
                'id' => 'row-1',
                'columns' => [
                    [
                        'id' => 'col-1',
                        'width' => '100%',
                        'elements' => [
                            [
                                'id' => 'el-1',
                                'type' => 'text',
                                'content' => ['text' => '<script>alert("x")</script>Safe copy'],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                        ],
                    ],
                ],
                'style' => [],
            ],
        ],
    ]);

    expect($html)->toContain('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;Safe copy');
    expect($html)->not->toContain('<script>alert("x")</script>');
});

it('falls back to # for invalid urls', function () {
    $html = app(EmailTemplateBuilderRenderer::class)->render([
        'schema_version' => 2,
        'theme' => [],
        'rows' => [
            [
                'id' => 'row-1',
                'columns' => [
                    [
                        'id' => 'col-1',
                        'width' => '100%',
                        'elements' => [
                            [
                                'id' => 'el-1',
                                'type' => 'button',
                                'content' => ['text' => 'Click', 'url' => 'not-a-valid-url'],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                        ],
                    ],
                ],
                'style' => [],
            ],
        ],
    ]);

    expect($html)->toContain('href="#"');
});

it('renders two columns with mobile stack fallback styles', function () {
    $html = app(EmailTemplateBuilderRenderer::class)->render([
        'schema_version' => 2,
        'theme' => [],
        'rows' => [
            [
                'id' => 'row-1',
                'columns' => [
                    [
                        'id' => 'left',
                        'width' => '50%',
                        'elements' => [
                            [
                                'id' => 'left-text',
                                'type' => 'text',
                                'content' => ['text' => 'Left'],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                        ],
                    ],
                    [
                        'id' => 'right',
                        'width' => '50%',
                        'elements' => [
                            [
                                'id' => 'right-text',
                                'type' => 'text',
                                'content' => ['text' => 'Right'],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                        ],
                    ],
                ],
                'style' => [],
            ],
        ],
    ]);

    expect($html)->toContain('width:50%');
    expect($html)->toContain('class="stack-column"');
});

it('applies theme values to wrapper output', function () {
    $html = app(EmailTemplateBuilderRenderer::class)->render([
        'schema_version' => 2,
        'theme' => [
            'content_width' => 700,
            'background_color' => '#0f172a',
            'surface_color' => '#111827',
            'text_color' => '#e5e7eb',
            'heading_color' => '#f9fafb',
            'font_family' => 'Tahoma, sans-serif',
        ],
        'rows' => [
            [
                'id' => 'row-1',
                'columns' => [
                    [
                        'id' => 'col-1',
                        'width' => '100%',
                        'elements' => [
                            [
                                'id' => 'el-1',
                                'type' => 'text',
                                'content' => ['text' => 'Theme preview'],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                        ],
                    ],
                ],
                'style' => [],
            ],
        ],
    ]);

    expect($html)->toContain('max-width:700px');
    expect($html)->toContain('background:#0f172a');
    expect($html)->toContain('font-family:Tahoma, sans-serif');
});
