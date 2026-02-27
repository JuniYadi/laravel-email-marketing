<?php

use App\Models\LandingPageTemplate;
use App\Models\User;
use Livewire\Livewire;

it('rejects unknown form data fields', function () {
    $this->actingAs(User::factory()->create());

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->set('title', 'Validation Page')
        ->set('slug', 'validation-page')
        ->set('meta.title', 'Validation Page')
        ->set('formData.headline', 'Valid headline')
        ->set('formData.injected', 'malicious')
        ->call('saveDraft')
        ->assertHasErrors(['formData']);
});

it('validates url fields from template schema', function () {
    $this->actingAs(User::factory()->create());

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
                ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'required' => true],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->set('title', 'Validation Page')
        ->set('slug', 'validation-page-2')
        ->set('meta.title', 'Validation Page')
        ->set('formData.headline', 'Valid headline')
        ->set('formData.cta_url', 'not-a-url')
        ->call('saveDraft')
        ->assertHasErrors(['formData.cta_url']);
});

it('rejects custom domain outside configured allowlist', function () {
    $this->actingAs(User::factory()->create());

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
                ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'required' => true],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->set('title', 'Domain Validation')
        ->set('slug', 'domain-validation')
        ->set('customDomain', 'invalid.example.net')
        ->set('meta.title', 'Domain Validation')
        ->set('formData.headline', 'Hello')
        ->set('formData.cta_url', 'https://example.com')
        ->call('saveDraft')
        ->assertHasErrors(['customDomain']);
});
