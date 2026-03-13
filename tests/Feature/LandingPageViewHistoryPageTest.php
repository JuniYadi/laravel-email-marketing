<?php

use App\Models\LandingPage;
use App\Models\LandingPageView;
use App\Models\User;
use Livewire\Livewire;

it('requires authentication for landing page history page', function () {
    $this->get(route('landing-pages.history'))
        ->assertRedirect(route('login'));
});

it('filters view history by landing page and traffic type from query params', function () {
    $this->actingAs(User::factory()->create());

    $landingPage = LandingPage::factory()->published()->create([
        'title' => 'Primary Event Page',
        'slug' => 'primary-event-page',
    ]);

    $otherPage = LandingPage::factory()->published()->create([
        'title' => 'Other Event Page',
        'slug' => 'other-event-page',
    ]);

    LandingPageView::factory()->create([
        'landing_page_id' => $landingPage->id,
        'ip_address' => '203.0.113.100',
        'user_agent' => 'Mozilla/5.0',
        'is_bot' => false,
        'viewed_at' => now()->subMinutes(5),
    ]);

    LandingPageView::factory()->create([
        'landing_page_id' => $landingPage->id,
        'ip_address' => '203.0.113.101',
        'user_agent' => 'Googlebot/2.1',
        'is_bot' => true,
        'viewed_at' => now()->subMinute(),
    ]);

    LandingPageView::factory()->create([
        'landing_page_id' => $otherPage->id,
        'ip_address' => '203.0.113.102',
        'user_agent' => 'Mozilla/5.0',
        'is_bot' => false,
        'viewed_at' => now(),
    ]);

    Livewire::withQueryParams([
        'landing_page_id' => (string) $landingPage->id,
        'traffic' => 'real',
    ])->test('pages::landing-pages.history')
        ->assertSet('landingPageFilter', (string) $landingPage->id)
        ->assertSet('trafficFilter', 'real')
        ->assertSee('Primary Event Page')
        ->assertSee('203.0.113.100')
        ->assertDontSee('203.0.113.101')
        ->assertDontSee('203.0.113.102');
});

it('supports pagination and search on landing page view history', function () {
    $this->actingAs(User::factory()->create());

    $landingPage = LandingPage::factory()->published()->create([
        'title' => 'Pagination Event Page',
        'slug' => 'pagination-event-page',
    ]);

    LandingPageView::factory()->count(30)->create([
        'landing_page_id' => $landingPage->id,
        'ip_address' => '198.51.100.1',
        'user_agent' => 'Mozilla/5.0',
        'is_bot' => false,
        'viewed_at' => now()->subMinutes(20),
    ]);

    LandingPageView::factory()->create([
        'landing_page_id' => $landingPage->id,
        'ip_address' => '198.51.100.2',
        'user_agent' => 'SpecialAgent/1.0',
        'is_bot' => false,
        'viewed_at' => now(),
    ]);

    $component = Livewire::test('pages::landing-pages.history')
        ->set('landingPageFilter', (string) $landingPage->id);

    $firstPage = $component->get('historyViews');

    expect($firstPage->total())->toBe(31)
        ->and($firstPage->perPage())->toBe(25)
        ->and(count($firstPage->items()))->toBe(25);

    $component->set('perPage', 50);

    $expandedPage = $component->get('historyViews');

    expect($expandedPage->total())->toBe(31)
        ->and($expandedPage->perPage())->toBe(50)
        ->and(count($expandedPage->items()))->toBe(31);

    $component->set('search', 'SpecialAgent/1.0');
    $searchPage = $component->get('historyViews');

    expect($searchPage->total())->toBe(1)
        ->and((string) collect($searchPage->items())->first()->ip_address)->toBe('198.51.100.2');
});
