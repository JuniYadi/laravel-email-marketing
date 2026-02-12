<?php

use App\Support\TemplateBuilder\SchemaMigrator;

it('converts schema v1 blocks into schema v2 rows', function () {
    $schema = app(SchemaMigrator::class)->migrate([
        'schema_version' => 1,
        'template_key' => 'welcome',
        'theme' => [],
        'blocks' => [
            [
                'id' => 'legacy-text',
                'type' => 'text',
                'content' => ['text' => 'Legacy body'],
            ],
            [
                'id' => 'legacy-button',
                'type' => 'cta_button',
                'content' => ['text' => 'Open', 'url' => 'https://example.com'],
            ],
        ],
    ]);

    expect($schema)->toBeArray();
    expect($schema['schema_version'])->toBe(2);
    expect($schema['rows'][0]['columns'][0]['elements'][0]['type'])->toBe('text');
});

it('returns null for invalid schema payloads', function () {
    $schema = app(SchemaMigrator::class)->migrate([
        'schema_version' => 1,
        'blocks' => 'invalid',
    ]);

    expect($schema)->toBeNull();
});
