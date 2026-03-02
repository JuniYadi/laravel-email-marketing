<?php

use App\Models\LandingPageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('syncs landing page templates from filesystem into database', function () {
    $this->artisan('landing-pages:sync-templates --no-interaction')
        ->assertExitCode(0);

    expect(LandingPageTemplate::query()->count())->toBe(2);
    expect(LandingPageTemplate::query()->where('key', 'basic')->exists())->toBeTrue();
    expect(LandingPageTemplate::query()->where('key', 'template-event')->exists())->toBeTrue();
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
