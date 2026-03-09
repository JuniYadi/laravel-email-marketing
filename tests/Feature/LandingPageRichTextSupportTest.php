<?php

use App\Models\LandingPageTemplate;
use App\Models\User;
use App\Support\LandingPages\LandingPageRenderer;
use Livewire\Livewire;

it('sanitizes richtext links and scripts before rendering template-event cards', function () {
    $snapshot = [
        'view_path' => 'landing-page-templates.template-event.view',
        'schema' => [
            'fields' => [
                ['key' => 'program_description', 'type' => 'richtext', 'default' => ''],
                ['key' => 'event_format_details', 'type' => 'richtext', 'default' => ''],
                ['key' => 'modules_list', 'type' => 'richtext', 'default' => ''],
            ],
        ],
    ];

    $html = app(LandingPageRenderer::class)->render($snapshot, [
        'program_description' => '<p><strong>Accelerating</strong> plan<script>alert(1)</script><a href="javascript:alert(1)" onclick="alert(1)">Bad Link</a><a href="https://example.com">Safe Link</a></p>',
        'event_format_details' => '<p><strong>Format:</strong> Workshop</p>',
        'modules_list' => '<ul><li>Module A</li></ul>',
    ]);

    expect($html)
        ->toContain('<strong>Accelerating</strong>')
        ->toContain('href="https://example.com"')
        ->toContain('Safe Link')
        ->not->toContain('<script>')
        ->not->toContain('onclick=')
        ->not->toContain('javascript:alert(1)');
});

it('strips html tags from legacy textarea fields rendered in template-event cards', function () {
    $snapshot = [
        'view_path' => 'landing-page-templates.template-event.view',
        'schema' => [
            'fields' => [
                ['key' => 'program_description', 'type' => 'textarea', 'default' => ''],
                ['key' => 'event_format_details', 'type' => 'textarea', 'default' => ''],
                ['key' => 'modules_list', 'type' => 'textarea', 'default' => ''],
            ],
        ],
    ];

    $html = app(LandingPageRenderer::class)->render($snapshot, [
        'program_description' => '<strong>Program</strong><script>alert(1)</script> body',
        'event_format_details' => '<p>Format</p>',
        'modules_list' => '<ul><li>Module</li></ul>',
    ]);

    expect($html)
        ->toContain('Programalert(1) body')
        ->toContain('Format')
        ->toContain('Module')
        ->not->toContain('<script>')
        ->not->toContain('<strong>')
        ->not->toContain('<ul>');
});

it('renders a trix editor for richtext template fields in landing page editor', function () {
    $this->actingAs(User::factory()->create());

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                ['key' => 'program_description', 'label' => 'Program Description', 'type' => 'richtext', 'required' => true],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->assertSee('trix-editor', false)
        ->assertSee('x-on:trix-change', false);
});
