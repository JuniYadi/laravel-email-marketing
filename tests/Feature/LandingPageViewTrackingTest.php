<?php

use App\Models\LandingPage;
use App\Models\LandingPageView;
use App\Models\User;
use Livewire\Livewire;

it('logs a guest real-traffic view with ip, user agent, and timestamp', function () {
    $landingPage = LandingPage::factory()->published()->create([
        'slug' => 'guest-real-view',
    ]);

    $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)';
    $ipAddress = '203.0.113.10';

    $this->withHeaders([
        'User-Agent' => $userAgent,
    ])->withServerVariables([
        'REMOTE_ADDR' => $ipAddress,
    ])->get(route('events.show', $landingPage->slug))
        ->assertSuccessful();

    $this->assertDatabaseHas('landing_page_views', [
        'landing_page_id' => $landingPage->id,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'is_bot' => 0,
    ]);

    expect(LandingPageView::query()->where('landing_page_id', $landingPage->id)->count())->toBe(1);
});

it('logs bot access but flags it as bot traffic', function () {
    $landingPage = LandingPage::factory()->published()->create([
        'slug' => 'guest-bot-view',
    ]);

    $userAgent = 'Twitterbot/1.0';
    $ipAddress = '203.0.113.11';

    $this->withHeaders([
        'User-Agent' => $userAgent,
    ])->withServerVariables([
        'REMOTE_ADDR' => $ipAddress,
    ])->get(route('events.show', $landingPage->slug))
        ->assertSuccessful();

    $this->assertDatabaseHas('landing_page_views', [
        'landing_page_id' => $landingPage->id,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'is_bot' => 1,
    ]);
});

it('logs every guest hit without deduplication', function () {
    $landingPage = LandingPage::factory()->published()->create([
        'slug' => 'guest-multi-hit',
    ]);

    foreach (range(1, 3) as $_) {
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0',
        ])->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.12',
        ])->get(route('events.show', $landingPage->slug))
            ->assertSuccessful();
    }

    expect(LandingPageView::query()->where('landing_page_id', $landingPage->id)->count())->toBe(3);
});

it('does not log views for authenticated users', function () {
    $landingPage = LandingPage::factory()->published()->create([
        'slug' => 'auth-view-no-log',
    ]);

    $this->actingAs(User::factory()->create())
        ->withHeaders([
            'User-Agent' => 'Mozilla/5.0',
        ])->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.13',
        ])->get(route('events.show', $landingPage->slug))
        ->assertSuccessful();

    expect(LandingPageView::query()->where('landing_page_id', $landingPage->id)->count())->toBe(0);
});

it('shows real and bot counts in landing pages index', function () {
    $this->actingAs(User::factory()->create());

    $landingPage = LandingPage::factory()->published()->create([
        'slug' => 'history-filter-page',
    ]);

    $otherPage = LandingPage::factory()->published()->create([
        'slug' => 'history-filter-other-page',
    ]);

    LandingPageView::factory()->create([
        'landing_page_id' => $landingPage->id,
        'ip_address' => '203.0.113.30',
        'user_agent' => 'Mozilla/5.0',
        'is_bot' => false,
        'viewed_at' => now()->subMinutes(10),
    ]);

    LandingPageView::factory()->create([
        'landing_page_id' => $landingPage->id,
        'ip_address' => '203.0.113.31',
        'user_agent' => 'Googlebot/2.1',
        'is_bot' => true,
        'viewed_at' => now()->subMinutes(5),
    ]);

    LandingPageView::factory()->create([
        'landing_page_id' => $landingPage->id,
        'ip_address' => '203.0.113.32',
        'user_agent' => 'Mozilla/5.0',
        'is_bot' => false,
        'viewed_at' => now()->subMinute(),
    ]);

    LandingPageView::factory()->create([
        'landing_page_id' => $otherPage->id,
        'ip_address' => '203.0.113.33',
        'user_agent' => 'Mozilla/5.0',
        'is_bot' => false,
        'viewed_at' => now(),
    ]);

    $component = Livewire::test('pages::landing-pages.index');

    $landingPages = $component->get('landingPages');
    $trackedPage = $landingPages->firstWhere('id', $landingPage->id);

    expect($trackedPage)->not->toBeNull()
        ->and((int) $trackedPage->real_views_count)->toBe(2)
        ->and((int) $trackedPage->bot_views_count)->toBe(1);

    $this->get(route('landing-pages.index'))
        ->assertOk()
        ->assertSee(route('landing-pages.history', ['landing_page_id' => $landingPage->id]), false);
});
