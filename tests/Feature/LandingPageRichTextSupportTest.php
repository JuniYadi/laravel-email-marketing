<?php

use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Models\User;
use App\Support\LandingPages\LandingPageRenderer;
use Livewire\Livewire;

it('sanitizes richtext links and scripts before rendering the basic template', function () {
    $snapshot = [
        'view_path' => 'landing-page-templates.basic.view',
        'schema' => [
            'fields' => [
                ['key' => 'headline', 'type' => 'text', 'default' => 'Hello'],
                ['key' => 'body', 'type' => 'richtext', 'default' => ''],
                ['key' => 'cta_label', 'type' => 'text', 'default' => 'Get Started'],
                ['key' => 'cta_url', 'type' => 'url', 'default' => 'https://example.com'],
                ['key' => 'background_color', 'type' => 'color', 'default' => '#0F172A'],
                ['key' => 'show_badge', 'type' => 'toggle', 'default' => true],
                ['key' => 'badge_text', 'type' => 'text', 'default' => 'Base Template'],
            ],
        ],
    ];

    $html = app(LandingPageRenderer::class)->render($snapshot, [
        'body' => '<p><strong>Accelerating</strong> plan<script>alert(1)</script><a href="javascript:alert(1)" onclick="alert(1)">Bad Link</a><a href="https://example.com">Safe Link</a></p>',
    ]);

    expect($html)
        ->toContain('Safe Link')
        ->toContain('https://example.com')
        ->not->toContain('<script>')
        ->not->toContain('onclick=')
        ->not->toContain('javascript:alert(1)');
});

it('strips html tags from textarea fields before rendering the basic template', function () {
    $snapshot = [
        'view_path' => 'landing-page-templates.basic.view',
        'schema' => [
            'fields' => [
                ['key' => 'headline', 'type' => 'text', 'default' => 'Hello'],
                ['key' => 'body', 'type' => 'textarea', 'default' => ''],
                ['key' => 'cta_label', 'type' => 'text', 'default' => 'Get Started'],
                ['key' => 'cta_url', 'type' => 'url', 'default' => 'https://example.com'],
                ['key' => 'background_color', 'type' => 'color', 'default' => '#0F172A'],
                ['key' => 'show_badge', 'type' => 'toggle', 'default' => true],
                ['key' => 'badge_text', 'type' => 'text', 'default' => 'Base Template'],
            ],
        ],
    ];

    $html = app(LandingPageRenderer::class)->render($snapshot, [
        'body' => '<strong>Program</strong><script>alert(1)</script> body',
    ]);

    expect($html)
        ->toContain('Programalert(1) body')
        ->not->toContain('<script>')
        ->not->toContain('<strong>');
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

it('hydrates stored richtext value when opening landing page edit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                ['key' => 'program_description', 'label' => 'Program Description', 'type' => 'richtext', 'required' => true],
            ],
        ],
    ]);

    $landingPage = LandingPage::factory()->create([
        'user_id' => $user->id,
        'landing_page_template_id' => $template->id,
        'template_snapshot' => [
            'key' => $template->key,
            'name' => $template->name,
            'description' => $template->description,
            'view_path' => $template->view_path,
            'version' => $template->version,
            'schema' => is_array($template->schema) ? $template->schema : ['fields' => []],
        ],
        'form_data' => [
            'program_description' => '<p><strong>Stored rich text</strong> value</p>',
        ],
    ]);

    Livewire::test('pages::landing-pages.editor', ['landingPage' => $landingPage->id])
        ->assertSet('formData.program_description', '<p><strong>Stored rich text</strong> value</p>')
        ->assertSee('&lt;p&gt;&lt;strong&gt;Stored rich text&lt;/strong&gt; value&lt;/p&gt;', false);
});
