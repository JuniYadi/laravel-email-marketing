<?php

use App\Models\LandingPageTemplate;
use App\Support\LandingPages\LandingPageTemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
