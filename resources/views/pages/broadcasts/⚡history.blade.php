<?php

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastRecipientEvent;
use App\Models\ContactGroup;
use App\Models\EmailTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $searchEmail = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'broadcast_id')]
    public string $broadcastFilter = '';

    public string $broadcastSearch = '';

    #[Url(as: 'group_id')]
    public string $groupFilter = '';

    #[Url(as: 'template_id')]
    public string $templateFilter = '';

    #[Url(as: 'date_from')]
    public string $dateFrom = '';

    #[Url(as: 'date_to')]
    public string $dateTo = '';

    #[Url(as: 'failed_only')]
    public bool $failedOnly = false;

    #[Url(as: 'per_page')]
    public int $perPage = 25;

    public ?int $activeRecipientId = null;

    public bool $showEventsModal = false;

    /**
     * Initialize filter UI state.
     */
    public function mount(): void
    {
        if ($this->broadcastFilter === '') {
            return;
        }

        $broadcastName = Broadcast::query()
            ->whereKey((int) $this->broadcastFilter)
            ->value('name');

        if (is_string($broadcastName)) {
            $this->broadcastSearch = $broadcastName;
        }
    }

    /**
     * Keep pagination stable when filters change.
     */
    public function updated(string $property): void
    {
        if (in_array($property, [
            'searchEmail',
            'statusFilter',
            'broadcastFilter',
            'broadcastSearch',
            'groupFilter',
            'templateFilter',
            'dateFrom',
            'dateTo',
            'failedOnly',
            'perPage',
        ], true)) {
            if (! in_array($this->perPage, [25, 50, 100], true)) {
                $this->perPage = 25;
            }

            $this->resetPage();
        }
    }

    /**
     * Clear selected broadcast when user starts typing a new search query.
     */
    public function updatedBroadcastSearch(string $value): void
    {
        if ($this->broadcastFilter === '') {
            return;
        }

        if (trim($value) === '') {
            $this->broadcastFilter = '';

            return;
        }

        $selectedName = Broadcast::query()
            ->whereKey((int) $this->broadcastFilter)
            ->value('name');

        if (! is_string($selectedName) || $selectedName !== $value) {
            $this->broadcastFilter = '';
        }
    }

    /**
     * Reset all filters to defaults.
     */
    public function resetFilters(): void
    {
        $this->reset(
            'searchEmail',
            'statusFilter',
            'broadcastFilter',
            'broadcastSearch',
            'groupFilter',
            'templateFilter',
            'dateFrom',
            'dateTo',
            'failedOnly',
        );

        $this->statusFilter = 'all';
        $this->perPage = 25;
        $this->resetPage();
    }

    /**
     * Select one broadcast from search results.
     */
    public function selectBroadcast(int $broadcastId): void
    {
        $broadcast = Broadcast::query()
            ->select(['id', 'name'])
            ->findOrFail($broadcastId);

        $this->broadcastFilter = (string) $broadcast->id;
        $this->broadcastSearch = $broadcast->name;
        $this->resetPage();
    }

    /**
     * Clear selected broadcast filter and search text.
     */
    public function clearBroadcastFilter(): void
    {
        $this->broadcastFilter = '';
        $this->broadcastSearch = '';
        $this->resetPage();
    }

    /**
     * Show full event history for one recipient.
     */
    public function openEventsModal(int $recipientId): void
    {
        $this->activeRecipientId = $recipientId;
        $this->showEventsModal = true;
    }

    /**
     * Requeue failed-like recipients for the currently selected broadcast.
     */
    public function requeueFailedLikeRecipients(): void
    {
        $this->resetErrorBag('broadcastFilter');

        if ($this->broadcastFilter === '') {
            $this->addError('broadcastFilter', __('Select a broadcast before requeueing recipients.'));

            return;
        }

        $broadcast = Broadcast::query()->findOrFail((int) $this->broadcastFilter);

        if ($broadcast->status !== Broadcast::STATUS_RUNNING) {
            $broadcast->status = Broadcast::STATUS_RUNNING;
            $broadcast->completed_at = null;

            if ($broadcast->started_at === null) {
                $broadcast->started_at = now();
            }

            $broadcast->save();
        }

        $requeuedCount = $this->filteredRecipientsQuery()
            ->where('broadcast_id', $broadcast->id)
            ->whereIn('status', $this->failedLikeStatuses())
            ->update([
                'status' => BroadcastRecipient::STATUS_PENDING,
                'queued_at' => null,
                'failed_at' => null,
                'last_error' => null,
            ]);

        session()->flash(
            'history_notice',
            __('Requeued :count recipients for :broadcast.', [
                'count' => $requeuedCount,
                'broadcast' => $broadcast->name,
            ]),
        );
    }

    /**
     * Export filtered recipient history to CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $filename = sprintf('broadcast-history-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'broadcast_id',
                'broadcast_name',
                'group_id',
                'group_name',
                'template_id',
                'template_name',
                'email',
                'status',
                'attempt_count',
                'provider_message_id',
                'queued_at',
                'sent_at',
                'delivered_at',
                'opened_at',
                'clicked_at',
                'failed_at',
                'skipped_at',
                'last_error',
            ]);

            $this->filteredRecipientsQuery()
                ->with([
                    'broadcast:id,name,contact_group_id,email_template_id',
                    'broadcast.group:id,name',
                    'broadcast.template:id,name',
                ])
                ->orderBy('id')
                ->chunkById(500, function (Collection $recipients) use ($handle): void {
                    foreach ($recipients as $recipient) {
                        fputcsv($handle, [
                            $recipient->broadcast_id,
                            $recipient->broadcast?->name,
                            $recipient->broadcast?->contact_group_id,
                            $recipient->broadcast?->group?->name,
                            $recipient->broadcast?->email_template_id,
                            $recipient->broadcast?->template?->name,
                            $recipient->email,
                            $recipient->status,
                            $recipient->attempt_count,
                            $recipient->provider_message_id,
                            $recipient->queued_at?->format('Y-m-d H:i:s'),
                            $recipient->sent_at?->format('Y-m-d H:i:s'),
                            $recipient->delivered_at?->format('Y-m-d H:i:s'),
                            $recipient->opened_at?->format('Y-m-d H:i:s'),
                            $recipient->clicked_at?->format('Y-m-d H:i:s'),
                            $recipient->failed_at?->format('Y-m-d H:i:s'),
                            $recipient->skipped_at?->format('Y-m-d H:i:s'),
                            $recipient->last_error,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Recipient rows for the history table.
     */
    #[Computed]
    public function historyRecipients(): LengthAwarePaginator
    {
        $perPage = in_array($this->perPage, [25, 50, 100], true) ? $this->perPage : 25;

        return $this->filteredRecipientsQuery()
            ->with([
                'broadcast:id,name,contact_group_id,email_template_id',
                'broadcast.group:id,name',
                'broadcast.template:id,name',
            ])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Metrics from the current filtered result.
     *
     * @return array<string, int|float>
     */
    #[Computed]
    public function kpis(): array
    {
        $aggregate = $this->filteredRecipientsQuery()
            ->selectRaw(
                '
                COUNT(*) AS total_count,
                SUM(CASE WHEN status IN (?, ?, ?, ?, ?, ?) THEN 1 ELSE 0 END) AS sent_count,
                SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS click_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS bounce_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS complaint_count,
                SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) AS failed_like_count
                ',
                [
                    BroadcastRecipient::STATUS_SENT,
                    BroadcastRecipient::STATUS_DELIVERED,
                    BroadcastRecipient::STATUS_OPENED,
                    BroadcastRecipient::STATUS_CLICKED,
                    BroadcastRecipient::STATUS_BOUNCED,
                    BroadcastRecipient::STATUS_COMPLAINED,
                    BroadcastRecipient::STATUS_DELIVERED,
                    BroadcastRecipient::STATUS_OPENED,
                    BroadcastRecipient::STATUS_CLICKED,
                    BroadcastRecipient::STATUS_OPENED,
                    BroadcastRecipient::STATUS_CLICKED,
                    BroadcastRecipient::STATUS_CLICKED,
                    BroadcastRecipient::STATUS_BOUNCED,
                    BroadcastRecipient::STATUS_COMPLAINED,
                    BroadcastRecipient::STATUS_FAILED,
                    BroadcastRecipient::STATUS_BOUNCED,
                    BroadcastRecipient::STATUS_COMPLAINED,
                ],
            )
            ->first();

        $sentCount = (int) ($aggregate?->sent_count ?? 0);
        $deliveredCount = (int) ($aggregate?->delivered_count ?? 0);
        $openCount = (int) ($aggregate?->open_count ?? 0);
        $clickCount = (int) ($aggregate?->click_count ?? 0);
        $bounceCount = (int) ($aggregate?->bounce_count ?? 0);
        $complaintCount = (int) ($aggregate?->complaint_count ?? 0);

        return [
            'total_count' => (int) ($aggregate?->total_count ?? 0),
            'sent_count' => $sentCount,
            'delivered_count' => $deliveredCount,
            'open_count' => $openCount,
            'click_count' => $clickCount,
            'bounce_count' => $bounceCount,
            'complaint_count' => $complaintCount,
            'failed_like_count' => (int) ($aggregate?->failed_like_count ?? 0),
            'success_rate' => $this->percentage($deliveredCount, $sentCount),
            'bounce_rate' => $this->percentage($bounceCount, $sentCount),
            'complaint_rate' => $this->percentage($complaintCount, $sentCount),
            'open_rate' => $this->percentage($openCount, $deliveredCount),
            'click_rate' => $this->percentage($clickCount, $deliveredCount),
        ];
    }

    /**
     * Search results for selecting a broadcast filter.
     */
    #[Computed]
    public function broadcastSearchResults(): Collection
    {
        $search = trim($this->broadcastSearch);

        if ($search === '') {
            return Broadcast::query()
                ->whereRaw('1 = 0')
                ->get();
        }

        return Broadcast::query()
            ->select(['id', 'name'])
            ->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');

                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search);
                }
            })
            ->when($this->broadcastFilter !== '', function (Builder $query): void {
                $query->where('id', '!=', (int) $this->broadcastFilter);
            })
            ->latest('id')
            ->limit(20)
            ->get();
    }

    /**
     * Selected broadcast object from filter id.
     */
    #[Computed]
    public function selectedBroadcast(): ?Broadcast
    {
        if ($this->broadcastFilter === '') {
            return null;
        }

        return Broadcast::query()
            ->select(['id', 'name'])
            ->find((int) $this->broadcastFilter);
    }

    /**
     * Group options for filter dropdown.
     */
    #[Computed]
    public function groups(): Collection
    {
        return ContactGroup::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Template options for filter dropdown.
     */
    #[Computed]
    public function templates(): Collection
    {
        return EmailTemplate::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Available recipient statuses for filter dropdown.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function statuses(): array
    {
        return [
            BroadcastRecipient::STATUS_PENDING => __('Pending'),
            BroadcastRecipient::STATUS_QUEUED => __('Queued'),
            BroadcastRecipient::STATUS_SENT => __('Sent'),
            BroadcastRecipient::STATUS_DELIVERED => __('Delivered'),
            BroadcastRecipient::STATUS_OPENED => __('Opened'),
            BroadcastRecipient::STATUS_CLICKED => __('Clicked'),
            BroadcastRecipient::STATUS_FAILED => __('Failed'),
            BroadcastRecipient::STATUS_BOUNCED => __('Bounced'),
            BroadcastRecipient::STATUS_COMPLAINED => __('Complained'),
            BroadcastRecipient::STATUS_SKIPPED => __('Skipped'),
        ];
    }

    /**
     * Active recipient in event modal.
     */
    #[Computed]
    public function activeRecipient(): ?BroadcastRecipient
    {
        if ($this->activeRecipientId === null) {
            return null;
        }

        return BroadcastRecipient::query()
            ->with('broadcast:id,name')
            ->find($this->activeRecipientId);
    }

    /**
     * Events for active recipient in the modal.
     */
    #[Computed]
    public function activeRecipientEvents(): Collection
    {
        if ($this->activeRecipientId === null) {
            return BroadcastRecipientEvent::query()
                ->whereRaw('1 = 0')
                ->get();
        }

        return BroadcastRecipientEvent::query()
            ->where('broadcast_recipient_id', $this->activeRecipientId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Build a filtered recipients query.
     */
    protected function filteredRecipientsQuery(): Builder
    {
        $query = BroadcastRecipient::query();

        if (trim($this->searchEmail) !== '') {
            $query->where('email', 'like', '%'.trim($this->searchEmail).'%');
        }

        if ($this->statusFilter !== '' && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->failedOnly) {
            $query->whereIn('status', $this->failedLikeStatuses());
        }

        if ($this->broadcastFilter !== '') {
            $query->where('broadcast_id', (int) $this->broadcastFilter);
        }

        if ($this->groupFilter !== '') {
            $query->whereHas('broadcast', function (Builder $broadcastQuery): void {
                $broadcastQuery->where('contact_group_id', (int) $this->groupFilter);
            });
        }

        if ($this->templateFilter !== '') {
            $query->whereHas('broadcast', function (Builder $broadcastQuery): void {
                $broadcastQuery->where('email_template_id', (int) $this->templateFilter);
            });
        }

        $dateFrom = $this->normalizeDate($this->dateFrom);
        $dateTo = $this->normalizeDate($this->dateTo);

        if ($dateFrom !== null) {
            $query->whereRaw('date(COALESCE(sent_at, created_at)) >= ?', [$dateFrom]);
        }

        if ($dateTo !== null) {
            $query->whereRaw('date(COALESCE(sent_at, created_at)) <= ?', [$dateTo]);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    protected function failedLikeStatuses(): array
    {
        return [
            BroadcastRecipient::STATUS_FAILED,
            BroadcastRecipient::STATUS_BOUNCED,
            BroadcastRecipient::STATUS_COMPLAINED,
        ];
    }

    /**
     * Normalize date input for SQL filtering.
     */
    protected function normalizeDate(string $date): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return $date;
    }

    /**
     * Calculate percentage with a zero-safe denominator.
     */
    protected function percentage(int $value, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($value / $denominator) * 100, 2);
    }
};
?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6 lg:p-8">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Broadcast History') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Monitor sending outcomes, delivery quality, and recipient-level event logs across all broadcasts.') }}
                </flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button wire:click="exportCsv" variant="outline" icon="arrow-down-tray">
                    {{ __('Export CSV') }}
                </flux:button>
                <flux:button wire:click="requeueFailedLikeRecipients" variant="primary" icon="arrow-path">
                    {{ __('Requeue Failed-like') }}
                </flux:button>
            </div>
        </div>

        @if (session()->has('history_notice'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-700 dark:bg-emerald-950/40">
                <flux:text>{{ session('history_notice') }}</flux:text>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>{{ __('Total Filtered Recipients') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format((int) $this->kpis['total_count']) }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>{{ __('Success Rate') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format((float) $this->kpis['success_rate'], 2) }}%</flux:heading>
                <flux:text class="mt-1 text-xs">{{ __('Delivered / Sent') }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>{{ __('Bounce Rate') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format((float) $this->kpis['bounce_rate'], 2) }}%</flux:heading>
                <flux:text class="mt-1 text-xs">{{ __('Bounced / Sent') }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>{{ __('Complaint Rate') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format((float) $this->kpis['complaint_rate'], 2) }}%</flux:heading>
                <flux:text class="mt-1 text-xs">{{ __('Complaints / Sent') }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>{{ __('Open Rate') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format((float) $this->kpis['open_rate'], 2) }}%</flux:heading>
                <flux:text class="mt-1 text-xs">{{ __('Opened or Clicked / Delivered') }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>{{ __('Click Rate') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format((float) $this->kpis['click_rate'], 2) }}%</flux:heading>
                <flux:text class="mt-1 text-xs">{{ __('Clicked / Delivered') }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>{{ __('Sent') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format((int) $this->kpis['sent_count']) }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>{{ __('Failed-like') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format((int) $this->kpis['failed_like_count']) }}</flux:heading>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="mb-4 flex items-center justify-between gap-3">
                <flux:text class="text-sm">{{ __('Filters') }}</flux:text>
                <flux:button wire:click="resetFilters" variant="ghost" size="sm">
                    {{ __('Reset') }}
                </flux:button>
            </div>

            <div class="grid gap-4 lg:grid-cols-4">
                <flux:input wire:model.live.debounce.400ms="searchEmail" :label="__('Email')" type="search" placeholder="{{ __('Search email...') }}" />

                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model.live="statusFilter">
                        <flux:select.option value="all">{{ __('All Statuses') }}</flux:select.option>
                        @foreach ($this->statuses as $statusValue => $statusLabel)
                            <flux:select.option wire:key="status-filter-{{ $statusValue }}" value="{{ $statusValue }}">
                                {{ $statusLabel }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <div class="space-y-2">
                    <flux:input
                        wire:model.live.debounce.300ms="broadcastSearch"
                        :label="__('Broadcast Search')"
                        type="search"
                        placeholder="{{ __('Type broadcast name or ID...') }}"
                    />
                    @if ($this->selectedBroadcast !== null)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                            <span>
                                {{ __('Selected') }}: {{ $this->selectedBroadcast->name }}
                                <span class="text-zinc-500">#{{ $this->selectedBroadcast->id }}</span>
                            </span>
                            <flux:button wire:click="clearBroadcastFilter" size="xs" variant="ghost">
                                {{ __('Clear') }}
                            </flux:button>
                        </div>
                    @endif
                    @if ($this->broadcastSearch !== '' && $this->broadcastSearchResults->isNotEmpty())
                        <div class="max-h-48 overflow-auto rounded-lg border border-zinc-200 p-1 dark:border-zinc-700">
                            @foreach ($this->broadcastSearchResults as $broadcastResult)
                                <button
                                    type="button"
                                    wire:key="broadcast-search-result-{{ $broadcastResult->id }}"
                                    wire:click="selectBroadcast({{ $broadcastResult->id }})"
                                    class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                >
                                    <span>{{ $broadcastResult->name }}</span>
                                    <span class="text-xs text-zinc-500">#{{ $broadcastResult->id }}</span>
                                </button>
                            @endforeach
                        </div>
                    @elseif ($this->broadcastSearch !== '' && $this->selectedBroadcast === null)
                        <flux:text class="text-xs">{{ __('No broadcasts match your search.') }}</flux:text>
                    @endif
                    <flux:error name="broadcastFilter" />
                </div>

                <flux:field>
                    <flux:label>{{ __('Group') }}</flux:label>
                    <flux:select wire:model.live="groupFilter">
                        <flux:select.option value="">{{ __('All Groups') }}</flux:select.option>
                        @foreach ($this->groups as $group)
                            <flux:select.option wire:key="group-filter-{{ $group->id }}" value="{{ $group->id }}">
                                {{ $group->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Template') }}</flux:label>
                    <flux:select wire:model.live="templateFilter">
                        <flux:select.option value="">{{ __('All Templates') }}</flux:select.option>
                        @foreach ($this->templates as $template)
                            <flux:select.option wire:key="template-filter-{{ $template->id }}" value="{{ $template->id }}">
                                {{ $template->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:input wire:model.live="dateFrom" :label="__('Date From')" type="date" />
                <flux:input wire:model.live="dateTo" :label="__('Date To')" type="date" />

                <flux:field>
                    <flux:label>{{ __('Rows per Page') }}</flux:label>
                    <flux:select wire:model.live="perPage">
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                    </flux:select>
                </flux:field>

                <div class="flex items-end">
                    <flux:checkbox wire:model.live="failedOnly" :label="__('Failed only')" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading>{{ __('Recipient History') }}</flux:heading>

            @if ($this->historyRecipients->count() > 0)
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="py-2 pe-3">{{ __('Broadcast') }}</th>
                                <th class="py-2 pe-3">{{ __('Group') }}</th>
                                <th class="py-2 pe-3">{{ __('Template') }}</th>
                                <th class="py-2 pe-3">{{ __('Email') }}</th>
                                <th class="py-2 pe-3">{{ __('Status') }}</th>
                                <th class="py-2 pe-3">{{ __('Attempts') }}</th>
                                <th class="py-2 pe-3">{{ __('Sent At') }}</th>
                                <th class="py-2 pe-3">{{ __('Failed At') }}</th>
                                <th class="py-2 pe-3">{{ __('Error') }}</th>
                                <th class="py-2">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->historyRecipients as $recipient)
                                <tr wire:key="broadcast-history-recipient-{{ $recipient->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
                                    <td class="py-2 pe-3">{{ $recipient->broadcast?->name ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $recipient->broadcast?->group?->name ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $recipient->broadcast?->template?->name ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $recipient->email }}</td>
                                    <td class="py-2 pe-3">
                                        @if ($recipient->status === BroadcastRecipient::STATUS_DELIVERED || $recipient->status === BroadcastRecipient::STATUS_OPENED || $recipient->status === BroadcastRecipient::STATUS_CLICKED)
                                            <flux:badge color="emerald" size="sm">{{ Str::headline($recipient->status) }}</flux:badge>
                                        @elseif ($recipient->status === BroadcastRecipient::STATUS_BOUNCED || $recipient->status === BroadcastRecipient::STATUS_COMPLAINED || $recipient->status === BroadcastRecipient::STATUS_FAILED)
                                            <flux:badge color="rose" size="sm">{{ Str::headline($recipient->status) }}</flux:badge>
                                        @elseif ($recipient->status === BroadcastRecipient::STATUS_QUEUED)
                                            <flux:badge color="amber" size="sm">{{ Str::headline($recipient->status) }}</flux:badge>
                                        @else
                                            <flux:badge size="sm">{{ Str::headline($recipient->status) }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-3">{{ $recipient->attempt_count }}</td>
                                    <td class="py-2 pe-3">{{ $recipient->sent_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $recipient->failed_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                    <td class="max-w-xs truncate py-2 pe-3">{{ $recipient->last_error ?? '-' }}</td>
                                    <td class="py-2">
                                        <flux:button wire:click="openEventsModal({{ $recipient->id }})" variant="ghost" size="sm">
                                            {{ __('View Events') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <flux:pagination :paginator="$this->historyRecipients" />
                </div>
            @else
                <flux:text class="mt-4">{{ __('No recipients found for current filters.') }}</flux:text>
            @endif
        </div>
    </div>

    <flux:modal wire:model="showEventsModal" class="max-w-5xl">
        <div class="space-y-4">
            <flux:heading>
                {{ __('Recipient Events') }}
                @if ($this->activeRecipient !== null)
                    <span class="text-sm font-normal text-zinc-500">{{ $this->activeRecipient->email }}</span>
                @endif
            </flux:heading>

            @if ($this->activeRecipientEvents->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="py-2 pe-3">{{ __('Event') }}</th>
                                <th class="py-2 pe-3">{{ __('Occurred At') }}</th>
                                <th class="py-2 pe-3">{{ __('Message ID') }}</th>
                                <th class="py-2">{{ __('Payload') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->activeRecipientEvents as $event)
                                <tr wire:key="broadcast-history-event-{{ $event->id }}" class="border-b border-zinc-100 align-top dark:border-zinc-800">
                                    <td class="py-2 pe-3">{{ Str::headline($event->event_type) }}</td>
                                    <td class="py-2 pe-3">{{ $event->occurred_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $event->provider_message_id ?? '-' }}</td>
                                    <td class="py-2">
                                        <details>
                                            <summary class="cursor-pointer text-xs text-zinc-500">
                                                {{ __('View Payload JSON') }}
                                            </summary>
                                            <pre class="mt-2 max-h-56 overflow-auto rounded-lg bg-zinc-900 p-3 text-xs text-zinc-100">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:text>{{ __('No events available for this recipient yet.') }}</flux:text>
            @endif
        </div>
    </flux:modal>
</section>
