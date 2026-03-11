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

it('persists headline highlight and cta url as separate template fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $template = LandingPageTemplate::factory()->create([
        'key' => 'template-event-test',
        'schema' => [
            'fields' => [
                ['key' => 'headline_highlight', 'label' => 'Headline Highlight', 'type' => 'text', 'required' => true, 'default' => 'Sales & Innovation'],
                ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'required' => true, 'default' => 'https://example.com/default'],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->set('title', 'Event Launch')
        ->set('slug', 'event-launch')
        ->set('meta.title', 'Event Launch Meta')
        ->set('formData.headline_highlight', 'Independent Highlight Value')
        ->set('formData.cta_url', 'https://example.com/register-now')
        ->call('saveDraft')
        ->assertHasNoErrors()
        ->assertRedirect(route('landing-pages.index'));

    $landingPage = LandingPage::query()->where('slug', 'event-launch')->firstOrFail();

    expect(data_get($landingPage->form_data, 'headline_highlight'))->toBe('Independent Highlight Value');
    expect(data_get($landingPage->form_data, 'cta_url'))->toBe('https://example.com/register-now');
});

it('keeps template fields independent after switching templates in create flow', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $basicTemplate = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true, 'default' => 'Hello'],
                ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'required' => true, 'default' => 'https://example.com/basic'],
            ],
        ],
    ]);

    $eventTemplate = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                ['key' => 'headline_highlight', 'label' => 'Headline Highlight', 'type' => 'text', 'required' => true, 'default' => 'Sales & Innovation'],
                ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'required' => true, 'default' => 'https://example.com/event'],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $basicTemplate->id)
        ->set('formData.cta_url', 'https://example.com/from-basic')
        ->set('selectedTemplateId', $eventTemplate->id)
        ->set('title', 'Switch Template Event')
        ->set('slug', 'switch-template-event')
        ->set('meta.title', 'Switch Template Event Meta')
        ->set('formData.headline_highlight', 'Create Flow Highlight')
        ->set('formData.cta_url', 'https://example.com/create-flow-cta')
        ->call('saveDraft')
        ->assertHasNoErrors()
        ->assertRedirect(route('landing-pages.index'));

    $landingPage = LandingPage::query()->where('slug', 'switch-template-event')->firstOrFail();

    expect(data_get($landingPage->form_data, 'headline_highlight'))->toBe('Create Flow Highlight');
    expect(data_get($landingPage->form_data, 'cta_url'))->toBe('https://example.com/create-flow-cta');
});

it('opens preview modal and switches preview viewport in landing page editor', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::landing-pages.editor')
        ->assertSet('showPreviewModal', false)
        ->assertSet('previewViewport', 'desktop')
        ->call('openPreviewModal')
        ->assertSet('showPreviewModal', true)
        ->call('setPreviewViewport', 'mobile')
        ->assertSet('previewViewport', 'mobile')
        ->call('setPreviewViewport', 'invalid')
        ->assertSet('previewViewport', 'mobile');
});

it('shows landing pages from all users in index', function () {
    $viewer = User::factory()->create();
    $ownerA = User::factory()->create(['name' => 'Owner A']);
    $ownerB = User::factory()->create(['name' => 'Owner B']);

    LandingPage::factory()->create([
        'user_id' => $ownerA->id,
        'title' => 'Owner A Page',
        'slug' => 'owner-a-page',
    ]);

    LandingPage::factory()->create([
        'user_id' => $ownerB->id,
        'title' => 'Owner B Page',
        'slug' => 'owner-b-page',
    ]);

    $this->actingAs($viewer)
        ->get(route('landing-pages.index'))
        ->assertOk()
        ->assertSeeText('Owner A Page')
        ->assertSeeText('Owner B Page')
        ->assertSeeText('Owner A')
        ->assertSeeText('Owner B');
});

it('allows editing landing pages created by another user', function () {
    $editor = User::factory()->create();
    $owner = User::factory()->create();
    $template = LandingPageTemplate::factory()->create();
    $landingPage = LandingPage::factory()->create([
        'user_id' => $owner->id,
        'landing_page_template_id' => $template->id,
        'title' => 'Shared Page',
        'slug' => 'shared-page',
        'template_snapshot' => [
            'key' => $template->key,
            'name' => $template->name,
            'description' => $template->description,
            'view_path' => $template->view_path,
            'version' => $template->version,
            'schema' => is_array($template->schema) ? $template->schema : ['fields' => []],
        ],
    ]);

    $this->actingAs($editor);

    $this->get(route('landing-pages.edit', $landingPage))
        ->assertOk();

    Livewire::test('pages::landing-pages.editor', ['landingPage' => $landingPage->id])
        ->set('title', 'Shared Page Updated')
        ->set('meta.title', 'Shared Page Updated Meta')
        ->call('saveDraft')
        ->assertHasNoErrors()
        ->assertRedirect(route('landing-pages.index'));

    expect($landingPage->fresh()->title)->toBe('Shared Page Updated');
});

it('allows only the owner to delete a landing page', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $landingPage = LandingPage::factory()->create([
        'user_id' => $owner->id,
    ]);

    expect($owner->can('delete', $landingPage))->toBeTrue();
    expect($otherUser->can('delete', $landingPage))->toBeFalse();
});

it('sync templates button refreshes stale landing page snapshot schema in index page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $template = LandingPageTemplate::factory()->create([
        'key' => 'basic',
        'name' => 'Basic',
        'schema' => [
            'fields' => [
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
            ],
            'meta' => ['render_mode' => 'standalone'],
        ],
    ]);

    $landingPage = LandingPage::factory()->create([
        'landing_page_template_id' => $template->id,
        'template_snapshot' => [
            'key' => 'basic',
            'name' => 'Basic',
            'description' => 'Old snapshot',
            'view_path' => 'landing-page-templates.basic.view',
            'version' => 1,
            'schema' => [
                'fields' => [
                    ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
                    ['key' => 'legacy_field', 'label' => 'Legacy Field', 'type' => 'text', 'required' => false],
                ],
            ],
        ],
        'form_data' => [
            'headline' => 'About Us',
            'legacy_field' => 'Old value',
        ],
    ]);

    Livewire::test('pages::landing-pages.index')
        ->call('syncTemplates');

    $landingPage->refresh();

    expect(collect(data_get($landingPage->template_snapshot, 'schema.fields', []))->pluck('key')->contains('legacy_field'))->toBeFalse()
        ->and($landingPage->form_data)->toBe([
            'headline' => 'About Us',
        ]);
});
