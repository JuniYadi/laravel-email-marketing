<?php

use App\Models\LandingPage;
use App\Models\LandingPageTemplate;

it('keeps rendering from template snapshot after base template changes', function () {
    $template = LandingPageTemplate::factory()->create([
        'key' => 'basic',
        'name' => 'Basic',
        'view_path' => 'landing-page-templates.basic.view',
        'schema' => [
            'fields' => [
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
                ['key' => 'body', 'label' => 'Body', 'type' => 'textarea', 'required' => true],
                ['key' => 'cta_label', 'label' => 'CTA Label', 'type' => 'text', 'required' => true],
                ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'required' => true],
                ['key' => 'background_color', 'label' => 'Background Color', 'type' => 'color', 'required' => true],
            ],
        ],
    ]);

    $landingPage = LandingPage::factory()->published()->create([
        'landing_page_template_id' => $template->id,
        'slug' => 'snapshot-event',
        'form_data' => [
            'headline' => 'Snapshot Headline',
            'body' => 'Snapshot Body',
            'cta_label' => 'Join',
            'cta_url' => 'https://example.com/register',
            'background_color' => '#0F172A',
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
                ],
            ],
        ],
    ]);

    $template->update([
        'view_path' => 'landing-page-templates.missing.view',
        'version' => 2,
    ]);

    $this->get(route('events.show', $landingPage->slug))
        ->assertSuccessful()
        ->assertSee('Snapshot Headline')
        ->assertDontSee('Create your first landing page');
});
