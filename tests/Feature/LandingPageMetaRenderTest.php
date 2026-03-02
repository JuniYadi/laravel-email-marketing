<?php

use App\Models\LandingPage;

it('renders meta and robots tags for published landing page', function () {
    $landingPage = LandingPage::factory()->published()->create([
        'slug' => 'meta-event',
        'meta' => [
            'title' => 'Meta Event Title',
            'description' => 'Meta Event Description',
            'og_title' => 'Meta OG Title',
            'og_description' => 'Meta OG Description',
            'og_image' => 'https://example.com/og-image.jpg',
            'noindex' => true,
        ],
        'template_snapshot' => [
            'key' => 'basic',
            'name' => 'Basic',
            'view_path' => 'landing-page-templates.basic.view',
            'version' => 1,
            'schema' => [
                'fields' => [
                    ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
                    ['key' => 'body', 'label' => 'Body', 'type' => 'textarea', 'required' => true],
                    ['key' => 'cta_label', 'label' => 'CTA Label', 'type' => 'text', 'required' => true],
                    ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'required' => true],
                    ['key' => 'background_color', 'label' => 'Background Color', 'type' => 'color', 'required' => true],
                    ['key' => 'show_badge', 'label' => 'Show Badge', 'type' => 'toggle', 'required' => false],
                    ['key' => 'badge_text', 'label' => 'Badge Text', 'type' => 'text', 'required' => false],
                ],
            ],
        ],
        'form_data' => [
            'headline' => 'Meta Headline',
            'body' => 'Meta body',
            'cta_label' => 'Join',
            'cta_url' => 'https://example.com/join',
            'background_color' => '#0F172A',
            'show_badge' => true,
            'badge_text' => 'Meta Badge',
        ],
    ]);

    $response = $this->get(route('events.show', $landingPage->slug));

    $response->assertSuccessful();
    $response->assertSee('<title>Meta Event Title</title>', false);
    $response->assertSee('name="description" content="Meta Event Description"', false);
    $response->assertSee('property="og:title" content="Meta OG Title"', false);
    $response->assertSee('property="og:image" content="https://example.com/og-image.jpg"', false);
    $response->assertSee('name="robots" content="noindex, nofollow"', false);
});

it('does not include app vite assets for standalone landing templates', function () {
    $landingPage = LandingPage::factory()->published()->create([
        'slug' => 'standalone-event',
        'template_snapshot' => [
            'key' => 'standalone',
            'name' => 'Standalone',
            'view_path' => 'landing-page-templates.basic.view',
            'version' => 1,
            'schema' => [
                'meta' => [
                    'render_mode' => 'standalone',
                ],
                'fields' => [
                    ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
                    ['key' => 'body', 'label' => 'Body', 'type' => 'textarea', 'required' => true],
                    ['key' => 'cta_label', 'label' => 'CTA Label', 'type' => 'text', 'required' => true],
                    ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'required' => true],
                    ['key' => 'background_color', 'label' => 'Background Color', 'type' => 'color', 'required' => true],
                ],
            ],
        ],
        'form_data' => [
            'headline' => 'Standalone Headline',
            'body' => 'Standalone body',
            'cta_label' => 'Join',
            'cta_url' => 'https://example.com/join',
            'background_color' => '#0F172A',
        ],
    ]);

    $response = $this->get(route('events.show', $landingPage->slug));

    $response->assertSuccessful();
    $response->assertDontSee('/build/assets/app-', false);
    $response->assertSee('<body', false);
    $response->assertDontSee('min-h-screen bg-zinc-50 text-zinc-900 antialiased', false);
});
