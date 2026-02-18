<?php

use App\Models\BroadcastRecipient;
use App\Models\BroadcastRecipientEvent;
use App\Models\Contact;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $chartDays = 30;

    /**
     * Summary totals shown on dashboard cards.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function summaryCards(): array
    {
        return [
            'total_sent' => BroadcastRecipient::query()
                ->whereIn('status', $this->sentStatuses())
                ->count(),
            'total_delivered' => BroadcastRecipient::query()
                ->whereIn('status', $this->deliveredStatuses())
                ->count(),
            'total_bounced' => BroadcastRecipient::query()
                ->where('status', BroadcastRecipient::STATUS_BOUNCED)
                ->count(),
            'total_reject' => BroadcastRecipient::query()
                ->where('status', BroadcastRecipient::STATUS_FAILED)
                ->count(),
            'total_complaint' => BroadcastRecipient::query()
                ->where('status', BroadcastRecipient::STATUS_COMPLAINED)
                ->count(),
            'total_contacts' => Contact::query()->count(),
        ];
    }

    /**
     * Chart payload for the daily trend chart.
     *
     * @return array{
     *     labels: list<string>,
     *     dates: list<string>,
     *     datasets: list<array{key: string, label: string, color: string, data: list<int>}>
     * }
     */
    #[Computed]
    public function chartPayload(): array
    {
        $chartDays = max(1, $this->chartDays);
        $startDate = now()->subDays($chartDays - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $seriesDefinition = $this->chartSeriesDefinition();
        $eventTypeToSeriesKey = [];

        foreach ($seriesDefinition as $seriesKey => $seriesMeta) {
            $eventTypeToSeriesKey[$seriesMeta['event_type']] = $seriesKey;
        }

        $dateKeys = [];
        for ($offset = 0; $offset < $chartDays; $offset++) {
            $dateKeys[] = $startDate->copy()->addDays($offset)->toDateString();
        }

        /** @var array<string, int> $dateIndexMap */
        $dateIndexMap = array_flip($dateKeys);

        /** @var array<string, list<int>> $seriesData */
        $seriesData = [];
        foreach (array_keys($seriesDefinition) as $seriesKey) {
            $seriesData[$seriesKey] = array_fill(0, $chartDays, 0);
        }

        $rows = BroadcastRecipientEvent::query()
            ->selectRaw('date(occurred_at) as event_date, event_type, count(*) as aggregate_count')
            ->whereNotNull('occurred_at')
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->whereIn('event_type', array_column($seriesDefinition, 'event_type'))
            ->groupBy('event_date', 'event_type')
            ->get();

        foreach ($rows as $row) {
            $eventType = (string) $row->event_type;
            $dateKey = (string) $row->event_date;
            $seriesKey = $eventTypeToSeriesKey[$eventType] ?? null;
            $dateIndex = $dateIndexMap[$dateKey] ?? null;

            if ($seriesKey === null || $dateIndex === null) {
                continue;
            }

            $seriesData[$seriesKey][$dateIndex] = (int) $row->aggregate_count;
        }

        $datasets = [];
        foreach ($seriesDefinition as $seriesKey => $seriesMeta) {
            $datasets[] = [
                'key' => $seriesKey,
                'label' => $seriesMeta['label'],
                'color' => $seriesMeta['color'],
                'data' => $seriesData[$seriesKey],
            ];
        }

        return [
            'labels' => array_map(
                fn (string $date): string => Carbon::parse($date)->format('M d'),
                $dateKeys,
            ),
            'dates' => $dateKeys,
            'datasets' => $datasets,
        ];
    }

    /**
     * Chart series metadata.
     *
     * @return array<string, array{label: string, event_type: string, color: string}>
     */
    protected function chartSeriesDefinition(): array
    {
        return [
            'send' => [
                'label' => 'Send',
                'event_type' => BroadcastRecipientEvent::TYPE_SENT,
                'color' => '#2563eb',
            ],
            'delivered' => [
                'label' => 'Delivered',
                'event_type' => BroadcastRecipientEvent::TYPE_DELIVERY,
                'color' => '#0891b2',
            ],
            'bounce' => [
                'label' => 'Bounce',
                'event_type' => BroadcastRecipientEvent::TYPE_BOUNCE,
                'color' => '#ea580c',
            ],
            'reject' => [
                'label' => 'Reject',
                'event_type' => BroadcastRecipientEvent::TYPE_SEND_FAILED,
                'color' => '#dc2626',
            ],
            'complaint' => [
                'label' => 'Complaint',
                'event_type' => BroadcastRecipientEvent::TYPE_COMPLAINT,
                'color' => '#be185d',
            ],
            'open' => [
                'label' => 'Open',
                'event_type' => BroadcastRecipientEvent::TYPE_OPEN,
                'color' => '#16a34a',
            ],
            'click' => [
                'label' => 'Click',
                'event_type' => BroadcastRecipientEvent::TYPE_CLICK,
                'color' => '#7c3aed',
            ],
        ];
    }

    /**
     * Statuses counted as sent.
     *
     * @return list<string>
     */
    protected function sentStatuses(): array
    {
        return [
            BroadcastRecipient::STATUS_SENT,
            BroadcastRecipient::STATUS_DELIVERED,
            BroadcastRecipient::STATUS_OPENED,
            BroadcastRecipient::STATUS_CLICKED,
            BroadcastRecipient::STATUS_BOUNCED,
            BroadcastRecipient::STATUS_COMPLAINED,
        ];
    }

    /**
     * Statuses counted as delivered.
     *
     * @return list<string>
     */
    protected function deliveredStatuses(): array
    {
        return [
            BroadcastRecipient::STATUS_DELIVERED,
            BroadcastRecipient::STATUS_OPENED,
            BroadcastRecipient::STATUS_CLICKED,
        ];
    }

    /**
     * Export dashboard summary and trend metrics to CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $filename = sprintf('dashboard-metrics-%s.csv', now()->format('Ymd_His'));
        $chartPayload = $this->chartPayload;
        $datasets = collect($chartPayload['datasets'] ?? [])->keyBy('key');
        $dates = $chartPayload['dates'] ?? [];

        return response()->streamDownload(function () use ($datasets, $dates): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'date',
                'send',
                'delivered',
                'bounce',
                'reject',
                'complaint',
                'open',
                'click',
            ], ',', '"', '');

            foreach ($dates as $index => $date) {
                fputcsv($handle, [
                    $date,
                    (int) ($datasets->get('send')['data'][$index] ?? 0),
                    (int) ($datasets->get('delivered')['data'][$index] ?? 0),
                    (int) ($datasets->get('bounce')['data'][$index] ?? 0),
                    (int) ($datasets->get('reject')['data'][$index] ?? 0),
                    (int) ($datasets->get('complaint')['data'][$index] ?? 0),
                    (int) ($datasets->get('open')['data'][$index] ?? 0),
                    (int) ($datasets->get('click')['data'][$index] ?? 0),
                ], ',', '"', '');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
};
?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Dashboard Overview') }}</flux:heading>
                <flux:text>{{ __('Track all-time deliverability metrics and daily email performance trends.') }}</flux:text>
            </div>

            <flux:button wire:click="exportCsv" variant="outline" icon="arrow-down-tray">
                {{ __('Export CSV') }}
            </flux:button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>{{ __('Total Sent') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format($this->summaryCards['total_sent']) }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>{{ __('Total Delivered') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format($this->summaryCards['total_delivered']) }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>{{ __('Total Bounced') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format($this->summaryCards['total_bounced']) }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>{{ __('Total Reject') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format($this->summaryCards['total_reject']) }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>{{ __('Total Complaint') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format($this->summaryCards['total_complaint']) }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>{{ __('Total Contacts') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format($this->summaryCards['total_contacts']) }}</flux:heading>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <flux:heading>{{ __('Daily Email Performance') }}</flux:heading>
                <flux:text class="text-sm">{{ __('Last :days days', ['days' => $chartDays]) }}</flux:text>
            </div>

            <div
                class="mt-4 h-96"
                data-dashboard-chart
                data-chart-payload="{{ json_encode($this->chartPayload, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT) }}"
            >
                <canvas data-chart-canvas></canvas>
            </div>
        </div>
    </div>
</section>
