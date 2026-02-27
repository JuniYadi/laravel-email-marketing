<?php

use App\Http\Controllers\LandingPageController;
use App\Models\LandingPage;
use App\Support\LandingPages\LandingPageRenderer;
use Illuminate\Http\Request;

it('returns not found for unpublished landing pages', function () {
    $landingPage = LandingPage::factory()->create([
        'slug' => 'internal-event',
        'status' => LandingPage::STATUS_DRAFT,
        'published_at' => null,
    ]);

    $this->get(route('events.show', $landingPage->slug))->assertNotFound();
});

it('renders published landing page by slug', function () {
    $landingPage = LandingPage::factory()->published()->create([
        'slug' => 'public-event',
        'form_data' => [
            'headline' => 'Public Event Headline',
            'body' => 'Open to all',
            'cta_label' => 'Register',
            'cta_url' => 'https://example.com/register',
            'background_color' => '#0F172A',
            'show_badge' => true,
            'badge_text' => 'Public',
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
    ]);

    $this->get(route('events.show', $landingPage->slug))
        ->assertSuccessful()
        ->assertSee('Public Event Headline');
});

it('renders published landing page from exact custom domain root', function () {
    LandingPage::factory()->published()->create([
        'custom_domain' => 'event.example.com',
        'form_data' => [
            'headline' => 'Domain Headline',
            'body' => 'Domain body',
            'cta_label' => 'Join',
            'cta_url' => 'https://example.com/join',
            'background_color' => '#0F172A',
            'show_badge' => true,
            'badge_text' => 'Domain Badge',
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
    ]);

    $response = app(LandingPageController::class)->showForHost(
        Request::create('http://event.example.com/', 'GET'),
        app(LandingPageRenderer::class),
    );

    expect($response->render())->toContain('Domain Headline');
});

it('renders published landing page from wildcard subdomain root', function () {
    LandingPage::factory()->published()->create([
        'custom_domain' => 'marketing.example.com',
        'form_data' => [
            'headline' => 'Wildcard Headline',
            'body' => 'Wildcard body',
            'cta_label' => 'Join',
            'cta_url' => 'https://example.com/join',
            'background_color' => '#0F172A',
            'show_badge' => false,
            'badge_text' => '',
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
    ]);

    $response = app(LandingPageController::class)->showForSubdomain(
        Request::create('http://marketing.example.com/', 'GET'),
        'marketing',
        app(LandingPageRenderer::class),
    );

    expect($response->render())->toContain('Wildcard Headline');
});
