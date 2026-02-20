<?php

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\ContactGroup;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    public bool $showCreateBroadcastModal = false;

    public bool $showRecipientsModal = false;

    public bool $showDuplicateModal = false;

    public ?int $activeBroadcastId = null;

    public ?int $duplicateBroadcastId = null;

    public string $duplicateName = '';

    public string $duplicateSnapshotChoice = 'template';

    public bool $showEditBroadcastModal = false;

    public ?int $editingBroadcastId = null;

    public string $editBroadcastName = '';

    public string $editBroadcastGroupId = '';

    public string $editBroadcastTemplateId = '';

    public string $editBroadcastReplyTo = '';

    public string $editBroadcastFromName = '';

    public string $editBroadcastFromPrefix = '';

    public string $editBroadcastFromDomain = '';

    public int $editBroadcastMessagesPerMinute = 1;

    public string $editBroadcastStartDate = '';

    public string $editBroadcastStartTime = '';

    public string $editBroadcastStartsAtTimezone = '';

    public string $broadcastName = '';

    public string $broadcastGroupId = '';

    public string $broadcastTemplateId = '';

    public string $broadcastReplyTo = '';

    public string $broadcastFromName = '';

    public string $broadcastFromPrefix = '';

    public string $broadcastFromDomain = '';

    public int $broadcastMessagesPerMinute = 1;

    public string $broadcastStartDate = '';

    public string $broadcastStartTime = '';

    public string $broadcastStartsAtTimezone = '';

    /**
     * Initialize defaults for broadcast form fields.
     */
    public function mount(): void
    {
        $this->broadcastFromDomain = $this->allowedDomains[0] ?? '';
        $this->broadcastStartsAtTimezone = $this->defaultScheduleTimezone();
    }

    /**
     * Open create broadcast modal.
     */
    public function openCreateBroadcastModal(): void
    {
        $this->showCreateBroadcastModal = true;
    }

    /**
     * Create a new broadcast.
     */
    public function createBroadcast(): void
    {
        $validated = $this->validate([
            'broadcastName' => ['required', 'string', 'max:255'],
            'broadcastGroupId' => ['required', 'integer', 'exists:contact_groups,id'],
            'broadcastTemplateId' => ['required', 'integer', 'exists:email_templates,id'],
            'broadcastReplyTo' => ['required', 'email'],
            'broadcastFromName' => ['required', 'string', 'max:255'],
            'broadcastFromPrefix' => ['required', 'string', 'max:64'],
            'broadcastFromDomain' => ['required', 'string', Rule::in($this->allowedDomains)],
            'broadcastMessagesPerMinute' => ['required', 'integer', 'min:1'],
            'broadcastStartDate' => ['required', 'date_format:Y-m-d'],
            'broadcastStartTime' => ['required', 'date_format:H:i'],
            'broadcastStartsAtTimezone' => ['required', 'timezone'],
        ]);

        $normalizedPrefix = Str::of($validated['broadcastFromPrefix'])
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        if ($normalizedPrefix === '') {
            $this->addError('broadcastFromPrefix', __('Only letters and numbers are allowed in sender prefix.'));

            return;
        }

        $scheduleTimezone = $this->resolveScheduleTimezone($validated['broadcastStartsAtTimezone']);
        $startsAt = $this->buildUtcStartAt(
            $validated['broadcastStartDate'],
            $validated['broadcastStartTime'],
            $scheduleTimezone,
        );

        Broadcast::query()->create([
            'name' => $validated['broadcastName'],
            'contact_group_id' => (int) $validated['broadcastGroupId'],
            'email_template_id' => (int) $validated['broadcastTemplateId'],
            'status' => Broadcast::STATUS_SCHEDULED,
            'starts_at' => $startsAt,
            'starts_at_timezone' => $scheduleTimezone,
            'messages_per_minute' => (int) $validated['broadcastMessagesPerMinute'],
            'reply_to' => $validated['broadcastReplyTo'],
            'from_name' => $validated['broadcastFromName'],
            'from_prefix' => $normalizedPrefix,
            'from_domain' => $validated['broadcastFromDomain'],
        ]);

        $this->resetBroadcastForm();
        $this->showCreateBroadcastModal = false;
        $this->dispatch('broadcast-created');
    }

    /**
     * Start or restart a broadcast immediately.
     */
    public function startBroadcast(int $broadcastId): void
    {
        $broadcast = Broadcast::query()->findOrFail($broadcastId);

        $broadcast->status = Broadcast::STATUS_RUNNING;
        $broadcast->completed_at = null;

        if ($broadcast->started_at === null) {
            $broadcast->started_at = now();
        }

        $broadcast->save();
    }

    /**
     * Pause a running broadcast.
     */
    public function pauseBroadcast(int $broadcastId): void
    {
        $broadcast = Broadcast::query()->findOrFail($broadcastId);
        $broadcast->status = Broadcast::STATUS_PAUSED;
        $broadcast->save();
    }

    /**
     * Resume a paused broadcast.
     */
    public function resumeBroadcast(int $broadcastId): void
    {
        $broadcast = Broadcast::query()->findOrFail($broadcastId);
        $broadcast->status = Broadcast::STATUS_RUNNING;

        if ($broadcast->started_at === null) {
            $broadcast->started_at = now();
        }

        $broadcast->save();
    }

    /**
     * Cancel a broadcast and stop further dispatch.
     */
    public function cancelBroadcast(int $broadcastId): void
    {
        $broadcast = Broadcast::query()->findOrFail($broadcastId);
        $broadcast->status = Broadcast::STATUS_CANCELLED;
        $broadcast->save();
    }

    /**
     * Open recipients modal for a broadcast.
     */
    public function openRecipientsModal(int $broadcastId): void
    {
        $this->activeBroadcastId = $broadcastId;
        $this->showRecipientsModal = true;
    }

    /**
     * Open duplicate modal for a broadcast.
     */
    public function openDuplicateModal(int $broadcastId): void
    {
        $broadcast = Broadcast::query()->findOrFail($broadcastId);

        $this->duplicateBroadcastId = $broadcast->id;
        $this->duplicateName = $broadcast->name;
        $this->duplicateSnapshotChoice = 'template';
        $this->showDuplicateModal = true;
    }

    /**
     * Duplicate a broadcast.
     */
    public function duplicateBroadcast(): void
    {
        $validated = $this->validate([
            'duplicateName' => ['required', 'string', 'max:255'],
        ]);

        $original = Broadcast::query()->findOrFail($this->duplicateBroadcastId);

        $copySnapshots = $this->duplicateSnapshotChoice === 'original';

        Broadcast::query()->create([
            'name' => $validated['duplicateName'],
            'contact_group_id' => $original->contact_group_id,
            'email_template_id' => $original->email_template_id,
            'status' => Broadcast::STATUS_DRAFT,
            'messages_per_minute' => $original->messages_per_minute,
            'reply_to' => $original->reply_to,
            'from_name' => $original->from_name,
            'from_prefix' => $original->from_prefix,
            'from_domain' => $original->from_domain,
            'snapshot_subject' => $copySnapshots ? $original->snapshot_subject : null,
            'snapshot_html_content' => $copySnapshots ? $original->snapshot_html_content : null,
            'snapshot_builder_schema' => $copySnapshots ? $original->snapshot_builder_schema : null,
            'snapshot_template_version' => $copySnapshots ? $original->snapshot_template_version : null,
        ]);

        $this->reset('duplicateBroadcastId', 'duplicateName', 'duplicateSnapshotChoice');
        $this->showDuplicateModal = false;
        $this->dispatch('broadcast-created');
    }

    /**
     * Open edit modal for a broadcast.
     */
    public function openEditBroadcastModal(int $broadcastId): void
    {
        $broadcast = Broadcast::query()->findOrFail($broadcastId);

        if (! in_array($broadcast->status, [Broadcast::STATUS_DRAFT, Broadcast::STATUS_SCHEDULED], true)) {
            return;
        }

        $this->editingBroadcastId = $broadcast->id;
        $this->editBroadcastName = $broadcast->name;
        $this->editBroadcastGroupId = (string) $broadcast->contact_group_id;
        $this->editBroadcastTemplateId = (string) $broadcast->email_template_id;
        $this->editBroadcastReplyTo = $broadcast->reply_to ?? '';
        $this->editBroadcastFromName = $broadcast->from_name ?? '';
        $this->editBroadcastFromPrefix = $broadcast->from_prefix ?? '';
        $this->editBroadcastFromDomain = $broadcast->from_domain ?? $this->allowedDomains[0] ?? '';
        $this->editBroadcastMessagesPerMinute = $broadcast->messages_per_minute ?? 1;
        $editScheduleTimezone = $this->resolveScheduleTimezone($broadcast->starts_at_timezone);
        $this->editBroadcastStartsAtTimezone = $editScheduleTimezone;
        $this->editBroadcastStartDate = $broadcast->starts_at?->timezone($editScheduleTimezone)->format('Y-m-d') ?? '';
        $this->editBroadcastStartTime = $broadcast->starts_at?->timezone($editScheduleTimezone)->format('H:i') ?? '';
        $this->showEditBroadcastModal = true;
    }

    /**
     * Update an existing broadcast.
     */
    public function updateBroadcast(): void
    {
        $validated = $this->validate([
            'editBroadcastName' => ['required', 'string', 'max:255'],
            'editBroadcastGroupId' => ['required', 'integer', 'exists:contact_groups,id'],
            'editBroadcastTemplateId' => ['required', 'integer', 'exists:email_templates,id'],
            'editBroadcastReplyTo' => ['required', 'email'],
            'editBroadcastFromName' => ['required', 'string', 'max:255'],
            'editBroadcastFromPrefix' => ['required', 'string', 'max:64'],
            'editBroadcastFromDomain' => ['required', 'string', Rule::in($this->allowedDomains)],
            'editBroadcastMessagesPerMinute' => ['required', 'integer', 'min:1'],
            'editBroadcastStartDate' => ['required', 'date_format:Y-m-d'],
            'editBroadcastStartTime' => ['required', 'date_format:H:i'],
            'editBroadcastStartsAtTimezone' => ['required', 'timezone'],
        ]);

        $normalizedPrefix = Str::of($validated['editBroadcastFromPrefix'])
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        if ($normalizedPrefix === '') {
            $this->addError('editBroadcastFromPrefix', __('Only letters and numbers are allowed in sender prefix.'));

            return;
        }

        $scheduleTimezone = $this->resolveScheduleTimezone($validated['editBroadcastStartsAtTimezone']);
        $startsAt = $this->buildUtcStartAt(
            $validated['editBroadcastStartDate'],
            $validated['editBroadcastStartTime'],
            $scheduleTimezone,
        );

        $broadcast = Broadcast::query()->findOrFail($this->editingBroadcastId);

        if (! in_array($broadcast->status, [Broadcast::STATUS_DRAFT, Broadcast::STATUS_SCHEDULED], true)) {
            $this->showEditBroadcastModal = false;

            return;
        }

        $broadcast->update([
            'name' => $validated['editBroadcastName'],
            'contact_group_id' => (int) $validated['editBroadcastGroupId'],
            'email_template_id' => (int) $validated['editBroadcastTemplateId'],
            'starts_at' => $startsAt,
            'starts_at_timezone' => $scheduleTimezone,
            'messages_per_minute' => (int) $validated['editBroadcastMessagesPerMinute'],
            'reply_to' => $validated['editBroadcastReplyTo'],
            'from_name' => $validated['editBroadcastFromName'],
            'from_prefix' => $normalizedPrefix,
            'from_domain' => $validated['editBroadcastFromDomain'],
        ]);

        $this->reset(
            'editingBroadcastId',
            'editBroadcastName',
            'editBroadcastGroupId',
            'editBroadcastTemplateId',
            'editBroadcastReplyTo',
            'editBroadcastFromName',
            'editBroadcastFromPrefix',
            'editBroadcastStartDate',
            'editBroadcastStartTime',
            'editBroadcastStartsAtTimezone',
        );
        $this->editBroadcastFromDomain = $this->allowedDomains[0] ?? '';
        $this->editBroadcastMessagesPerMinute = 1;
        $this->showEditBroadcastModal = false;
        $this->dispatch('broadcast-updated');
    }

    /**
     * Requeue failed or stale queued recipients for a broadcast.
     */
    public function requeueBroadcast(int $broadcastId): void
    {
        $broadcast = Broadcast::query()->findOrFail($broadcastId);

        $broadcast->status = Broadcast::STATUS_RUNNING;
        $broadcast->completed_at = null;

        if ($broadcast->started_at === null) {
            $broadcast->started_at = now();
        }

        $broadcast->save();

        BroadcastRecipient::query()
            ->where('broadcast_id', $broadcast->id)
            ->where(function ($query): void {
                $query->where('status', BroadcastRecipient::STATUS_FAILED)
                    ->orWhere('status', BroadcastRecipient::STATUS_QUEUED);
            })
            ->whereNull('sent_at')
            ->update([
                'status' => BroadcastRecipient::STATUS_PENDING,
                'queued_at' => null,
                'failed_at' => null,
                'last_error' => null,
            ]);
    }

    /**
     * Export broadcast list with progress and status to CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $filename = sprintf('broadcasts-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'id',
                'name',
                'group_id',
                'group_name',
                'template_id',
                'template_name',
                'messages_per_minute',
                'starts_at',
                'starts_at_timezone',
                'processed_recipients_count',
                'total_recipients_count',
                'status',
                'reply_to',
                'from_name',
                'from_email',
                'created_at',
            ], ',', '"', '');

            Broadcast::query()
                ->with(['group:id,name', 'template:id,name'])
                ->withCount([
                    'recipients as total_recipients_count',
                    'recipients as processed_recipients_count' => function ($query): void {
                        $query->whereIn('status', [
                            BroadcastRecipient::STATUS_SENT,
                            BroadcastRecipient::STATUS_DELIVERED,
                            BroadcastRecipient::STATUS_OPENED,
                            BroadcastRecipient::STATUS_CLICKED,
                            BroadcastRecipient::STATUS_FAILED,
                            BroadcastRecipient::STATUS_BOUNCED,
                            BroadcastRecipient::STATUS_COMPLAINED,
                            BroadcastRecipient::STATUS_SKIPPED,
                        ]);
                    },
                ])
                ->orderBy('id')
                ->chunkById(500, function (Collection $broadcasts) use ($handle): void {
                    foreach ($broadcasts as $broadcast) {
                        fputcsv($handle, [
                            $broadcast->id,
                            $broadcast->name,
                            $broadcast->contact_group_id,
                            $broadcast->group?->name,
                            $broadcast->email_template_id,
                            $broadcast->template?->name,
                            $broadcast->messages_per_minute,
                            $this->formatBroadcastStartAt($broadcast),
                            $this->resolveScheduleTimezone($broadcast->starts_at_timezone),
                            (int) $broadcast->processed_recipients_count,
                            (int) $broadcast->total_recipients_count,
                            $broadcast->status,
                            $broadcast->reply_to,
                            $broadcast->from_name,
                            $broadcast->from_email,
                            $broadcast->created_at?->format('Y-m-d H:i:s'),
                        ], ',', '"', '');
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Reset create broadcast form fields.
     */
    protected function resetBroadcastForm(): void
    {
        $this->reset(
            'broadcastName',
            'broadcastGroupId',
            'broadcastTemplateId',
            'broadcastReplyTo',
            'broadcastFromName',
            'broadcastFromPrefix',
            'broadcastStartDate',
            'broadcastStartTime',
            'broadcastStartsAtTimezone',
        );

        $this->broadcastFromDomain = $this->allowedDomains[0] ?? '';
        $this->broadcastMessagesPerMinute = 1;
        $this->broadcastStartsAtTimezone = $this->defaultScheduleTimezone();
    }

    protected function defaultScheduleTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    protected function resolveScheduleTimezone(?string $timezone): string
    {
        $fallbackTimezone = $this->defaultScheduleTimezone();

        if (! is_string($timezone) || $timezone === '') {
            return $fallbackTimezone;
        }

        if (! in_array($timezone, $this->timezones, true)) {
            return $fallbackTimezone;
        }

        return $timezone;
    }

    protected function buildUtcStartAt(string $date, string $time, string $timezone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', sprintf('%s %s', $date, $time), $timezone)
            ->utc();
    }

    public function formatBroadcastStartAt(Broadcast $broadcast): string
    {
        if ($broadcast->starts_at === null) {
            return '-';
        }

        $scheduleTimezone = $this->resolveScheduleTimezone($broadcast->starts_at_timezone);

        return sprintf(
            '%s (%s)',
            $broadcast->starts_at->timezone($scheduleTimezone)->format('Y-m-d H:i'),
            $scheduleTimezone,
        );
    }

    #[Computed]
    public function broadcasts(): Collection
    {
        return Broadcast::query()
            ->with(['group:id,name', 'template:id,name', 'recipients:id,broadcast_id,status'])
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function groups(): Collection
    {
        return ContactGroup::query()->orderBy('name')->get();
    }

    #[Computed]
    public function templates(): Collection
    {
        return EmailTemplate::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function allowedDomains(): array
    {
        $domains = config('broadcast.allowed_domains', []);

        if ($domains !== []) {
            return $domains;
        }

        $fallbackFromAddress = (string) config('mail.from.address');
        $parts = explode('@', $fallbackFromAddress);

        if (isset($parts[1]) && $parts[1] !== '') {
            return [$parts[1]];
        }

        return ['example.com'];
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function timezones(): array
    {
        return \DateTimeZone::listIdentifiers();
    }

    #[Computed]
    public function activeBroadcastRecipients(): Collection
    {
        if ($this->activeBroadcastId === null) {
            return BroadcastRecipient::query()
                ->whereRaw('1 = 0')
                ->get();
        }

        return BroadcastRecipient::query()
            ->where('broadcast_id', $this->activeBroadcastId)
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function recipientCount(): int
    {
        if ($this->broadcastGroupId === '') {
            return 0;
        }

        $group = ContactGroup::find((int) $this->broadcastGroupId);
        if (! $group) {
            return 0;
        }

        return $group->contacts()
            ->where('is_invalid', false)
            ->count();
    }
};
?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Broadcasts') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Schedule, rate-limit, and track email sends per group.') }}</flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button wire:click="exportCsv" variant="outline" icon="arrow-down-tray">
                    {{ __('Export CSV') }}
                </flux:button>
                <flux:button wire:click="openCreateBroadcastModal" variant="primary">
                    {{ __('Create Broadcast') }}
                </flux:button>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading>{{ __('Broadcast List') }}</flux:heading>

            @if ($this->broadcasts->isNotEmpty())
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="py-2 pe-3">{{ __('Name') }}</th>
                                <th class="py-2 pe-3">{{ __('Group') }}</th>
                                <th class="py-2 pe-3">{{ __('Template') }}</th>
                                <th class="py-2 pe-3">{{ __('Rate') }}</th>
                                <th class="py-2 pe-3">{{ __('Start At') }}</th>
                                <th class="py-2 pe-3">{{ __('Progress') }}</th>
                                <th class="py-2 pe-3">{{ __('Status') }}</th>
                                <th class="py-2">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->broadcasts as $broadcast)
                                @php
                                    $totalRecipients = $broadcast->recipients->count();
                                    $processedStatuses = [
                                        BroadcastRecipient::STATUS_SENT,
                                        BroadcastRecipient::STATUS_DELIVERED,
                                        BroadcastRecipient::STATUS_OPENED,
                                        BroadcastRecipient::STATUS_CLICKED,
                                        BroadcastRecipient::STATUS_FAILED,
                                        BroadcastRecipient::STATUS_BOUNCED,
                                        BroadcastRecipient::STATUS_COMPLAINED,
                                        BroadcastRecipient::STATUS_SKIPPED,
                                    ];
                                    $processedCount = $broadcast->recipients->whereIn('status', $processedStatuses)->count();
                                @endphp
                                <tr wire:key="broadcast-row-{{ $broadcast->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
                                    <td class="py-2 pe-3">{{ $broadcast->name }}</td>
                                    <td class="py-2 pe-3">{{ $broadcast->group?->name ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $broadcast->template?->name ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $broadcast->messages_per_minute }}/min</td>
                                    <td class="py-2 pe-3">{{ $this->formatBroadcastStartAt($broadcast) }}</td>
                                    <td class="py-2 pe-3">{{ $processedCount }}/{{ $totalRecipients }}</td>
                                    <td class="py-2 pe-3">
                                        @if ($broadcast->status === Broadcast::STATUS_RUNNING)
                                            <flux:badge color="emerald" size="sm">{{ __('Running') }}</flux:badge>
                                        @elseif ($broadcast->status === Broadcast::STATUS_PAUSED)
                                            <flux:badge color="amber" size="sm">{{ __('Paused') }}</flux:badge>
                                        @elseif ($broadcast->status === Broadcast::STATUS_COMPLETED)
                                            <flux:badge color="sky" size="sm">{{ __('Completed') }}</flux:badge>
                                        @elseif ($broadcast->status === Broadcast::STATUS_CANCELLED)
                                            <flux:badge color="rose" size="sm">{{ __('Cancelled') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm">{{ __('Scheduled') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        <div class="flex items-center gap-2">
                                            @if ($broadcast->status === Broadcast::STATUS_SCHEDULED)
                                                <flux:button wire:click="startBroadcast({{ $broadcast->id }})" size="sm" variant="primary">
                                                    {{ __('Start') }}
                                                </flux:button>
                                            @elseif ($broadcast->status === Broadcast::STATUS_RUNNING)
                                                <flux:button wire:click="pauseBroadcast({{ $broadcast->id }})" size="sm" variant="ghost">
                                                    {{ __('Pause') }}
                                                </flux:button>
                                            @elseif ($broadcast->status === Broadcast::STATUS_PAUSED)
                                                <flux:button wire:click="resumeBroadcast({{ $broadcast->id }})" size="sm" variant="primary">
                                                    {{ __('Resume') }}
                                                </flux:button>
                                            @endif

                                            <flux:dropdown position="bottom" align="end">
                                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />

                                                <flux:menu>
                                                    @if (in_array($broadcast->status, [Broadcast::STATUS_DRAFT, Broadcast::STATUS_SCHEDULED]))
                                                        <flux:menu.item wire:click="openEditBroadcastModal({{ $broadcast->id }})" icon="pencil">
                                                            {{ __('Edit') }}
                                                        </flux:menu.item>
                                                    @endif

                                                    <flux:menu.item wire:click="openDuplicateModal({{ $broadcast->id }})" icon="document-duplicate">
                                                        {{ __('Duplicate') }}
                                                    </flux:menu.item>

                                                    <flux:menu.item wire:click="openRecipientsModal({{ $broadcast->id }})" icon="users">
                                                        {{ __('Recipients') }}
                                                    </flux:menu.item>

                                                    <flux:menu.item :href="route('broadcasts.history', ['broadcast_id' => $broadcast->id])" icon="clock" wire:navigate>
                                                        {{ __('History') }}
                                                    </flux:menu.item>

                                                    @if (in_array($broadcast->status, [Broadcast::STATUS_COMPLETED, Broadcast::STATUS_PAUSED]))
                                                        <flux:menu.separator />
                                                        <flux:menu.item wire:click="requeueBroadcast({{ $broadcast->id }})" icon="arrow-path">
                                                            {{ __('Requeue Failed') }}
                                                        </flux:menu.item>
                                                    @endif

                                                    @if (in_array($broadcast->status, [Broadcast::STATUS_SCHEDULED, Broadcast::STATUS_PAUSED]))
                                                        <flux:menu.separator />
                                                        <flux:menu.item wire:click="cancelBroadcast({{ $broadcast->id }})" icon="x-mark">
                                                            {{ __('Cancel Broadcast') }}
                                                        </flux:menu.item>
                                                    @endif
                                                </flux:menu>
                                            </flux:dropdown>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:text class="mt-4">{{ __('No broadcasts yet. Create your first broadcast to start sending.') }}</flux:text>
            @endif
        </div>
    </div>

    <flux:modal wire:model="showCreateBroadcastModal" class="max-w-2xl">
        <div class="space-y-4">
            <flux:heading>{{ __('Create Broadcast') }}</flux:heading>

            <form wire:submit="createBroadcast" class="space-y-4">
                <flux:input wire:model="broadcastName" :label="__('Name')" type="text" required autofocus />

                <flux:field>
                    <flux:label>{{ __('Group') }}</flux:label>
                    <flux:select wire:model="broadcastGroupId" required>
                        <flux:select.option value="">{{ __('Choose Group') }}</flux:select.option>
                        @foreach ($this->groups as $group)
                            <flux:select.option wire:key="broadcast-group-{{ $group->id }}" value="{{ $group->id }}">
                                {{ $group->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="broadcastGroupId" />
                </flux:field>

                @if($this->broadcastGroupId !== '' && $this->recipientCount > 0)
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <flux:icon name="users" class="w-4 h-4" />
                        <span>This broadcast will reach {{ $this->recipientCount }} contacts</span>
                    </div>
                @elseif($this->broadcastGroupId !== '' && $this->recipientCount === 0)
                    <div class="flex items-center gap-2 text-sm text-orange-600">
                        <flux:icon name="exclamation-triangle" class="w-4 h-4" />
                        <span>No valid contacts found in this group</span>
                    </div>
                @endif

                <flux:field>
                    <flux:label>{{ __('Template') }}</flux:label>
                    <flux:select wire:model="broadcastTemplateId" required>
                        <flux:select.option value="">{{ __('Choose Template') }}</flux:select.option>
                        @foreach ($this->templates as $template)
                            <flux:select.option wire:key="broadcast-template-{{ $template->id }}" value="{{ $template->id }}">
                                {{ $template->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="broadcastTemplateId" />
                </flux:field>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="broadcastReplyTo" :label="__('Reply To')" type="email" required />
                    <flux:input wire:model="broadcastFromName" :label="__('From Name')" type="text" required />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="broadcastFromPrefix" :label="__('From Prefix')" type="text" required />

                    <flux:field>
                        <flux:label>{{ __('From Domain') }}</flux:label>
                        <flux:select wire:model="broadcastFromDomain" required>
                            @foreach ($this->allowedDomains as $domain)
                                <flux:select.option wire:key="broadcast-domain-{{ $domain }}" value="{{ $domain }}">
                                    {{ $domain }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="broadcastFromDomain" />
                    </flux:field>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <flux:input wire:model="broadcastMessagesPerMinute" :label="__('Messages Per Minute')" type="number" min="1" required />
                    <flux:input wire:model="broadcastStartDate" :label="__('Start Date')" type="date" required />
                    <flux:input wire:model="broadcastStartTime" :label="__('Start Time')" type="time" required />
                </div>
                <flux:field>
                    <flux:label>{{ __('Schedule Timezone') }}</flux:label>
                    <flux:select wire:model="broadcastStartsAtTimezone" required>
                        @foreach ($this->timezones as $timezone)
                            <flux:select.option wire:key="broadcast-timezone-{{ $timezone }}" value="{{ $timezone }}">
                                {{ $timezone }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:text class="mt-1 text-xs text-zinc-500">
                        {{ __('Dispatch checks run in UTC. Your selected timezone is converted to UTC automatically.') }}
                    </flux:text>
                    <flux:error name="broadcastStartDate" />
                    <flux:error name="broadcastStartTime" />
                    <flux:error name="broadcastStartsAtTimezone" />
                </flux:field>

                <div class="flex items-center justify-end gap-2">
                    <flux:button wire:click="$set('showCreateBroadcastModal', false)" variant="ghost" type="button">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ __('Save Broadcast') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal wire:model="showRecipientsModal" class="max-w-4xl">
        <div class="space-y-4">
            <flux:heading>{{ __('Broadcast Recipients') }}</flux:heading>

            @if ($this->activeBroadcastRecipients->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="py-2 pe-3">{{ __('Email') }}</th>
                                <th class="py-2 pe-3">{{ __('Status') }}</th>
                                <th class="py-2 pe-3">{{ __('Attempts') }}</th>
                                <th class="py-2 pe-3">{{ __('Queued At') }}</th>
                                <th class="py-2 pe-3">{{ __('Sent At') }}</th>
                                <th class="py-2 pe-3">{{ __('Error') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->activeBroadcastRecipients as $recipient)
                                <tr wire:key="recipient-row-{{ $recipient->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
                                    <td class="py-2 pe-3">{{ $recipient->email }}</td>
                                    <td class="py-2 pe-3">{{ Str::headline($recipient->status) }}</td>
                                    <td class="py-2 pe-3">{{ $recipient->attempt_count }}</td>
                                    <td class="py-2 pe-3">{{ $recipient->queued_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $recipient->sent_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $recipient->last_error ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:text>{{ __('No recipients found for this broadcast yet.') }}</flux:text>
            @endif
        </div>
    </flux:modal>

    <flux:modal wire:model="showDuplicateModal" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading>{{ __('Duplicate Broadcast') }}</flux:heading>

            <form wire:submit="duplicateBroadcast" class="space-y-4">
                <flux:input wire:model="duplicateName" :label="__('Name')" type="text" required autofocus />

                <flux:field>
                    <flux:label>{{ __('Email Content') }}</flux:label>
                    <flux:radio.group wire:model="duplicateSnapshotChoice" class="flex flex-col gap-3">
                        <flux:radio value="template" label="{{ __('Use current template version') }}" />
                        <flux:text class="mt-[-8px] ml-6 text-sm text-zinc-500">
                            {{ __('Gets latest subject and content from the template') }}
                        </flux:text>

                        <flux:radio value="original" label="{{ __('Use original email content') }}" />
                        <flux:text class="mt-[-8px] ml-6 text-sm text-zinc-500">
                            {{ __('Preserves exact email from this broadcast') }}
                        </flux:text>
                    </flux:radio.group>
                </flux:field>

                <div class="flex items-center justify-end gap-2">
                    <flux:button wire:click="$set('showDuplicateModal', false)" variant="ghost" type="button">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ __('Duplicate') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal wire:model="showEditBroadcastModal" class="max-w-2xl">
        <div class="space-y-4">
            <flux:heading>{{ __('Edit Broadcast') }}</flux:heading>

            <form wire:submit="updateBroadcast" class="space-y-4">
                <flux:input wire:model="editBroadcastName" :label="__('Name')" type="text" required autofocus />

                <flux:field>
                    <flux:label>{{ __('Group') }}</flux:label>
                    <flux:select wire:model="editBroadcastGroupId" required>
                        <flux:select.option value="">{{ __('Choose Group') }}</flux:select.option>
                        @foreach ($this->groups as $group)
                            <flux:select.option wire:key="edit-broadcast-group-{{ $group->id }}" value="{{ $group->id }}">
                                {{ $group->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="editBroadcastGroupId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Template') }}</flux:label>
                    <flux:select wire:model="editBroadcastTemplateId" required>
                        <flux:select.option value="">{{ __('Choose Template') }}</flux:select.option>
                        @foreach ($this->templates as $template)
                            <flux:select.option wire:key="edit-broadcast-template-{{ $template->id }}" value="{{ $template->id }}">
                                {{ $template->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="editBroadcastTemplateId" />
                </flux:field>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="editBroadcastReplyTo" :label="__('Reply To')" type="email" required />
                    <flux:input wire:model="editBroadcastFromName" :label="__('From Name')" type="text" required />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="editBroadcastFromPrefix" :label="__('From Prefix')" type="text" required />

                    <flux:field>
                        <flux:label>{{ __('From Domain') }}</flux:label>
                        <flux:select wire:model="editBroadcastFromDomain" required>
                            @foreach ($this->allowedDomains as $domain)
                                <flux:select.option wire:key="edit-broadcast-domain-{{ $domain }}" value="{{ $domain }}">
                                    {{ $domain }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editBroadcastFromDomain" />
                    </flux:field>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <flux:input wire:model="editBroadcastMessagesPerMinute" :label="__('Messages Per Minute')" type="number" min="1" required />
                    <flux:input wire:model="editBroadcastStartDate" :label="__('Start Date')" type="date" required />
                    <flux:input wire:model="editBroadcastStartTime" :label="__('Start Time')" type="time" required />
                </div>
                <flux:field>
                    <flux:label>{{ __('Schedule Timezone') }}</flux:label>
                    <flux:select wire:model="editBroadcastStartsAtTimezone" required>
                        @foreach ($this->timezones as $timezone)
                            <flux:select.option wire:key="edit-broadcast-timezone-{{ $timezone }}" value="{{ $timezone }}">
                                {{ $timezone }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:text class="mt-1 text-xs text-zinc-500">
                        {{ __('Dispatch checks run in UTC. Your selected timezone is converted to UTC automatically.') }}
                    </flux:text>
                    <flux:error name="editBroadcastStartDate" />
                    <flux:error name="editBroadcastStartTime" />
                    <flux:error name="editBroadcastStartsAtTimezone" />
                </flux:field>

                <div class="flex items-center justify-end gap-2">
                    <flux:button wire:click="$set('showEditBroadcastModal', false)" variant="ghost" type="button">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ __('Update Broadcast') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
