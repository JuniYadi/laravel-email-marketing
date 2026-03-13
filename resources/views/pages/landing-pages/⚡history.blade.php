<?php

use App\Models\LandingPage;
use App\Models\LandingPageView;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'landing_page_id')]
    public string $landingPageFilter = '';

    #[Url(as: 'traffic')]
    public string $trafficFilter = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'date_from')]
    public string $dateFrom = '';

    #[Url(as: 'date_to')]
    public string $dateTo = '';

    #[Url(as: 'per_page')]
    public int $perPage = 25;

    public function updated(string $property): void
    {
        if (! in_array($property, ['landingPageFilter', 'trafficFilter', 'search', 'dateFrom', 'dateTo', 'perPage'], true)) {
            return;
        }

        if (! in_array($this->trafficFilter, ['all', 'real', 'bot'], true)) {
            $this->trafficFilter = 'all';
        }

        if (! in_array($this->perPage, [25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage('views_page');
    }

    public function clearFilters(): void
    {
        $this->reset('landingPageFilter', 'trafficFilter', 'search', 'dateFrom', 'dateTo', 'perPage');
        $this->trafficFilter = 'all';
        $this->perPage = 25;
        $this->resetPage('views_page');
    }

    #[Computed]
    public function landingPages(): Collection
    {
        return LandingPage::query()
            ->select(['id', 'title'])
            ->latest('updated_at')
            ->get();
    }

    /**
     * @return array{total: int, real: int, bot: int}
     */
    #[Computed]
    public function kpis(): array
    {
        $baseQuery = $this->filteredViewsQuery();

        return [
            'total' => (clone $baseQuery)->count(),
            'real' => (clone $baseQuery)->where('is_bot', false)->count(),
            'bot' => (clone $baseQuery)->where('is_bot', true)->count(),
        ];
    }

    #[Computed]
    public function historyViews(): LengthAwarePaginator
    {
        $perPage = in_array($this->perPage, [25, 50, 100], true) ? $this->perPage : 25;

        return $this->filteredViewsQuery()
            ->with('landingPage:id,title,slug,custom_domain')
            ->orderByDesc('viewed_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'views_page');
    }

    public function publicPageUrl(LandingPage $landingPage): string
    {
        if (filled($landingPage->custom_domain)) {
            return 'https://'.$landingPage->custom_domain;
        }

        return route('events.show', $landingPage->slug);
    }

    protected function filteredViewsQuery(): Builder
    {
        return LandingPageView::query()
            ->when(
                $this->landingPageFilter !== '' && ctype_digit($this->landingPageFilter),
                fn (Builder $query): Builder => $query->where('landing_page_id', (int) $this->landingPageFilter),
            )
            ->when(
                $this->trafficFilter === 'real',
                fn (Builder $query): Builder => $query->where('is_bot', false),
            )
            ->when(
                $this->trafficFilter === 'bot',
                fn (Builder $query): Builder => $query->where('is_bot', true),
            )
            ->when(
                trim($this->search) !== '',
                function (Builder $query): Builder {
                    $searchTerm = '%'.trim($this->search).'%';

                    return $query->where(function (Builder $nestedQuery) use ($searchTerm): void {
                        $nestedQuery->where('ip_address', 'like', $searchTerm)
                            ->orWhere('user_agent', 'like', $searchTerm);
                    });
                },
            )
            ->when(
                $this->isIsoDate($this->dateFrom),
                fn (Builder $query): Builder => $query->whereDate('viewed_at', '>=', $this->dateFrom),
            )
            ->when(
                $this->isIsoDate($this->dateTo),
                fn (Builder $query): Builder => $query->whereDate('viewed_at', '<=', $this->dateTo),
            );
    }

    protected function isIsoDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}; ?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Landing Page View History') }}</flux:heading>
                <flux:text>{{ __('Browse and filter landing page traffic logs with bot classification.') }}</flux:text>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Total Views') }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ number_format($this->kpis['total']) }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Real Views') }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ number_format($this->kpis['real']) }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Bot Views') }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ number_format($this->kpis['bot']) }}</flux:heading>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <flux:heading size="sm">{{ __('Filters') }}</flux:heading>

                <flux:button wire:click="clearFilters" variant="ghost" size="sm">
                    {{ __('Clear') }}
                </flux:button>
            </div>

            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <flux:field>
                    <flux:label>{{ __('Landing Page') }}</flux:label>
                    <flux:select wire:model.live="landingPageFilter">
                        <flux:select.option value="">{{ __('All Pages') }}</flux:select.option>
                        @foreach ($this->landingPages as $landingPage)
                            <flux:select.option wire:key="history-page-filter-{{ $landingPage->id }}" value="{{ $landingPage->id }}">
                                {{ $landingPage->title }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Traffic') }}</flux:label>
                    <flux:select wire:model.live="trafficFilter">
                        <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                        <flux:select.option value="real">{{ __('Real') }}</flux:select.option>
                        <flux:select.option value="bot">{{ __('Bot') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:input
                    wire:model.live.debounce.300ms="search"
                    :label="__('Search IP / User Agent')"
                    type="search"
                    placeholder="{{ __('Search...') }}"
                    class="xl:col-span-2"
                />

                <flux:input wire:model.live="dateFrom" :label="__('Date From')" type="date" />
                <flux:input wire:model.live="dateTo" :label="__('Date To')" type="date" />

                <flux:field>
                    <flux:label>{{ __('Rows') }}</flux:label>
                    <flux:select wire:model.live="perPage">
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            @if ($this->historyViews->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="py-2 pe-3">{{ __('Landing Page') }}</th>
                                <th class="py-2 pe-3">{{ __('Traffic') }}</th>
                                <th class="py-2 pe-3">{{ __('Viewed At') }}</th>
                                <th class="py-2 pe-3">{{ __('IP Address') }}</th>
                                <th class="py-2 pe-3">{{ __('User Agent') }}</th>
                                <th class="py-2">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->historyViews as $viewEvent)
                                <tr wire:key="landing-page-history-view-{{ $viewEvent->id }}" class="border-b border-zinc-100 align-top dark:border-zinc-800">
                                    <td class="py-2 pe-3">
                                        {{ $viewEvent->landingPage?->title ?? '-' }}
                                    </td>
                                    <td class="py-2 pe-3">
                                        @if ($viewEvent->is_bot)
                                            <flux:badge color="amber" size="sm">{{ __('Bot') }}</flux:badge>
                                        @else
                                            <flux:badge color="emerald" size="sm">{{ __('Real') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-3">{{ $viewEvent->viewed_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $viewEvent->ip_address }}</td>
                                    <td class="py-2 pe-3">
                                        <div class="max-w-lg break-words">{{ $viewEvent->user_agent ?: '-' }}</div>
                                    </td>
                                    <td class="py-2">
                                        @if ($viewEvent->landingPage !== null)
                                            <flux:button :href="$this->publicPageUrl($viewEvent->landingPage)" variant="ghost" size="sm" target="_blank" rel="noreferrer">
                                                {{ __('Open Page') }}
                                            </flux:button>
                                        @else
                                            <span class="text-zinc-500">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <flux:pagination :paginator="$this->historyViews" />
                </div>
            @else
                <flux:text>{{ __('No view logs found for current filters.') }}</flux:text>
            @endif
        </div>
    </div>
</section>
