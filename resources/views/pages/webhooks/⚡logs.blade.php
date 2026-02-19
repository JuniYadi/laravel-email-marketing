<?php

use App\Models\SnsWebhookMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'type')]
    public string $messageType = 'all';

    #[Url(as: 'per_page')]
    public int $perPage = 25;

    /**
     * Keep pagination stable when filters change.
     */
    public function updated(string $property): void
    {
        if (! in_array($property, ['search', 'messageType', 'perPage'], true)) {
            return;
        }

        if (! in_array($this->perPage, [25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage();
    }

    /**
     * Reset current filters.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->messageType = 'all';
        $this->perPage = 25;
        $this->resetPage();
    }

    /**
     * Available SNS message types.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function messageTypes(): array
    {
        return [
            'all' => __('All types'),
            'Notification' => __('Notification'),
            'SubscriptionConfirmation' => __('Subscription Confirmation'),
            'UnsubscribeConfirmation' => __('Unsubscribe Confirmation'),
        ];
    }

    /**
     * Webhook log rows for the current filters.
     */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        $perPage = in_array($this->perPage, [25, 50, 100], true) ? $this->perPage : 25;
        $search = trim($this->search);

        return SnsWebhookMessage::query()
            ->when($this->messageType !== 'all', function (Builder $query): void {
                $query->where('message_type', $this->messageType);
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('message_id', 'like', '%'.$search.'%')
                        ->orWhere('topic_arn', 'like', '%'.$search.'%')
                        ->orWhere('message_type', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate($perPage);
    }
};
?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Webhook Logs') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Track all incoming SNS webhook messages with payload metadata for troubleshooting and auditing.') }}
                </flux:text>
            </div>
        </div>

        <div class="grid gap-3 rounded-xl border border-zinc-200 p-4 md:grid-cols-12 dark:border-zinc-700">
            <flux:field class="md:col-span-6">
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    :placeholder="__('Search by message ID, topic ARN, or type...')"
                />
            </flux:field>

            <flux:field class="md:col-span-3">
                <flux:label>{{ __('Type') }}</flux:label>
                <flux:select wire:model.live="messageType">
                    @foreach ($this->messageTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>{{ __('Per page') }}</flux:label>
                <flux:select wire:model.live="perPage">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </flux:select>
            </flux:field>

            <div class="flex items-end md:col-span-1">
                <flux:button wire:click="resetFilters" variant="ghost" class="w-full">
                    {{ __('Reset') }}
                </flux:button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-900">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">{{ __('Received') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">{{ __('Type') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">{{ __('Message ID') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">{{ __('Topic ARN') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">{{ __('Token Length') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">{{ __('Payload') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-950/40">
                        @forelse ($this->logs as $log)
                            <tr wire:key="sns-log-{{ $log->id }}">
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-200">
                                    {{ $log->created_at?->format('Y-m-d H:i:s') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge color="zinc">{{ $log->message_type }}</flux:badge>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-700 dark:text-zinc-200">
                                    {{ Str::limit((string) $log->message_id, 80) }}
                                </td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-200">
                                    <span class="break-all">{{ Str::limit((string) $log->topic_arn, 90) }}</span>
                                </td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-200">
                                    {{ strlen((string) $log->token) }}
                                </td>
                                <td class="px-4 py-3">
                                    <details class="group">
                                        <summary class="cursor-pointer text-xs font-medium text-zinc-600 underline-offset-4 hover:underline dark:text-zinc-300">
                                            {{ __('View JSON') }}
                                        </summary>
                                        <pre class="mt-2 max-h-56 overflow-auto rounded-md bg-zinc-100 p-3 text-xs text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-zinc-500 dark:text-zinc-400">
                                    {{ __('No webhook logs found for current filters.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                {{ $this->logs->links() }}
            </div>
        </div>
    </div>
</section>
