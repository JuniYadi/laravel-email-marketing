<?php

use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Models\User;
use Livewire\Livewire;

it('requires authentication for landing pages index', function () {
    $this->get(route('landing-pages.index'))->assertRedirect(route('login'));
});

it('requires authentication for landing pages create page', function () {
    $this->get(route('landing-pages.create'))->assertRedirect(route('login'));
});

it('requires authentication for landing pages edit page', function () {
    $landingPage = LandingPage::factory()->create();

    $this->get(route('landing-pages.edit', $landingPage))->assertRedirect(route('login'));
});

it('creates a draft landing page through the editor', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true, 'default' => 'Hello'],
                ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'required' => true, 'default' => 'https://example.com'],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->set('title', 'Spring Event')
        ->set('slug', 'spring-event')
        ->set('meta.title', 'Spring Event Meta')
        ->set('formData.headline', 'Spring Event Headline')
        ->set('formData.cta_url', 'https://example.com/register')
        ->call('saveDraft')
        ->assertHasNoErrors()
        ->assertRedirect(route('landing-pages.index'));

    $landingPage = LandingPage::query()->where('slug', 'spring-event')->first();

    expect($landingPage)->not->toBeNull();
    expect($landingPage?->user_id)->toBe($user->id);
    expect($landingPage?->status)->toBe(LandingPage::STATUS_DRAFT);
    expect($landingPage?->template_snapshot['key'])->toBe($template->key);
});
