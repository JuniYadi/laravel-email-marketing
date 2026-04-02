<?php

use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Support\LandingPages\LandingPageTemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('syncs landing page templates from filesystem into database', function () {
    $definitions = app(LandingPageTemplateRegistry::class)->definitions();

    $this->artisan('landing-pages:sync-templates --fail-on-invalid --no-interaction')
        ->assertExitCode(0);

    $syncedKeys = LandingPageTemplate::query()
        ->where('is_active', true)
        ->pluck('key')
        ->all();

    $expectedKeys = collect($definitions)
        ->pluck('key')
        ->all();

    expect($syncedKeys)->toHaveCount(count($expectedKeys));
    expect($syncedKeys)->toMatchArray($expectedKeys);
});

it('deactivates templates that are no longer in filesystem definitions', function () {
    LandingPageTemplate::factory()->create([
        'key' => 'retired-template',
        'is_active' => true,
    ]);

    $this->artisan('landing-pages:sync-templates --no-interaction')
        ->assertExitCode(0);

    expect(
        LandingPageTemplate::query()
            ->where('key', 'retired-template')
            ->value('is_active')
    )->toBeFalse();
});

it('can sync filesystem templates without deactivating existing database templates', function () {
    $template = LandingPageTemplate::factory()->create([
        'key' => 'retained-template',
        'is_active' => true,
    ]);

    app(LandingPageTemplateRegistry::class)->sync(false);

    expect($template->fresh()?->is_active)->toBeTrue();
});

it('skips automatic sync when template files are unchanged', function () {
    Cache::flush();

    $registry = app(LandingPageTemplateRegistry::class);

    $first = $registry->syncIfChanged(false);
    $second = $registry->syncIfChanged(false);

    expect($first['skipped'])->toBeFalse()
        ->and($second['skipped'])->toBeTrue()
        ->and($second['synced'])->toBe(0)
        ->and($second['deactivated'])->toBe(0);
});

it('fails sync command when metadata is invalid and fail-on-invalid is enabled', function () {
    $registry = \Mockery::mock(LandingPageTemplateRegistry::class);
    $registry->shouldReceive('sync')
        ->once()
        ->andThrow(new \InvalidArgumentException('Invalid template metadata'));

    $this->app->instance(LandingPageTemplateRegistry::class, $registry);

    $this->artisan('landing-pages:sync-templates --fail-on-invalid --no-interaction')
        ->expectsOutput('Invalid template metadata')
        ->assertExitCode(1);
});

it('refreshes existing landing page snapshots and removes stale form keys', function () {
    $template = LandingPageTemplate::factory()->create([
        'key' => 'basic',
        'name' => 'Basic',
        'description' => 'Updated schema',
        'view_path' => 'landing-page-templates.basic.view',
        'version' => 2,
        'schema' => [
            'fields' => [
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
                ['key' => 'body', 'label' => 'Body', 'type' => 'textarea', 'required' => true],
            ],
            'meta' => ['render_mode' => 'standalone'],
        ],
        'is_active' => true,
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

    $registry = \Mockery::mock(LandingPageTemplateRegistry::class);
    $registry->shouldReceive('sync')
        ->once()
        ->andReturn(['synced' => 0, 'deactivated' => 0]);

    $this->app->instance(LandingPageTemplateRegistry::class, $registry);

    $this->artisan('landing-pages:sync-templates --no-interaction')
        ->assertExitCode(0);

    $landingPage->refresh();

    expect(data_get($landingPage->template_snapshot, 'version'))->toBe(2)
        ->and(data_get($landingPage->template_snapshot, 'schema.fields.0.key'))->toBe('headline')
        ->and(collect(data_get($landingPage->template_snapshot, 'schema.fields', []))->pluck('key')->contains('legacy_field'))->toBeFalse()
        ->and($landingPage->form_data)->toBe([
            'headline' => 'About Us',
        ]);
});

it('migrates legacy template event cta fields into cta buttons during snapshot refresh', function () {
    $template = LandingPageTemplate::factory()->create([
        'key' => 'template-event',
        'name' => 'Template Event',
        'description' => 'Updated schema',
        'view_path' => 'landing-page-templates.template-event.view',
        'version' => 2,
        'schema' => [
            'fields' => [
                ['key' => 'headline_text', 'label' => 'Headline Text', 'type' => 'text', 'required' => true],
                ['key' => 'cards', 'label' => 'Cards', 'type' => 'repeater', 'required' => true],
                ['key' => 'cta_buttons', 'label' => 'CTA Buttons', 'type' => 'repeater', 'required' => true],
            ],
            'meta' => ['render_mode' => 'standalone'],
        ],
        'is_active' => true,
    ]);

    $landingPage = LandingPage::factory()->create([
        'landing_page_template_id' => $template->id,
        'template_snapshot' => [
            'key' => 'template-event',
            'name' => 'Template Event',
            'description' => 'Old snapshot',
            'view_path' => 'landing-page-templates.template-event.view',
            'version' => 1,
            'schema' => [
                'fields' => [
                    ['key' => 'headline_text', 'label' => 'Headline Text', 'type' => 'text', 'required' => true],
                    ['key' => 'cards', 'label' => 'Cards', 'type' => 'repeater', 'required' => true],
                    ['key' => 'cta_label', 'label' => 'CTA Label', 'type' => 'text', 'required' => true],
                    ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'required' => true],
                ],
            ],
        ],
        'form_data' => [
            'headline_text' => 'Event Headline',
            'cards' => [
                [
                    'order' => 1,
                    'title' => 'Program Description',
                    'content' => '<p>Program content</p>',
                ],
            ],
            'cta_label' => 'Reserve Seat',
            'cta_url' => 'https://example.com/reserve',
            'legacy_field' => 'Old value',
        ],
    ]);

    $registry = \Mockery::mock(LandingPageTemplateRegistry::class);
    $registry->shouldReceive('sync')
        ->once()
        ->andReturn(['synced' => 0, 'deactivated' => 0]);

    $this->app->instance(LandingPageTemplateRegistry::class, $registry);

    $this->artisan('landing-pages:sync-templates --no-interaction')
        ->assertExitCode(0);

    $landingPage->refresh();

    expect($landingPage->form_data)->toBe([
        'headline_text' => 'Event Headline',
        'cards' => [
            [
                'order' => 1,
                'title' => 'Program Description',
                'content' => '<p>Program content</p>',
            ],
        ],
        'cta_buttons' => [
            [
                'label' => 'Reserve Seat',
                'url' => 'https://example.com/reserve',
            ],
        ],
    ]);
});
