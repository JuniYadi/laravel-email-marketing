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

new class extends Component
{
    public bool $showCreateBroadcastModal = false;

    public bool $showRecipientsModal = false;

    public bool $showDuplicateModal = false;

    public ?int $activeBroadcastId = null;

    public ?int $duplicateBroadcastId = null;

    public string $duplicateName = '';

    public string $duplicateSnapshotChoice = 'template';

    public string $broadcastName = '';

    public string $broadcastGroupId = '';

    public string $broadcastTemplateId = '';

    public string $broadcastReplyTo = '';

    public string $broadcastFromName = '';

    public string $broadcastFromPrefix = '';

    public string $broadcastFromDomain = '';

    public int $broadcastMessagesPerMinute = 1;

    public string $broadcastStartsAt = '';

    /**
     * Initialize defaults for broadcast form fields.
     */
    public function mount(): void
    {
        $this->broadcastFromDomain = $this->allowedDomains[0] ?? '';
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
            'broadcastStartsAt' => ['required', 'date'],
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

        Broadcast::query()->create([
            'name' => $validated['broadcastName'],
            'contact_group_id' => (int) $validated['broadcastGroupId'],
            'email_template_id' => (int) $validated['broadcastTemplateId'],
            'status' => Broadcast::STATUS_SCHEDULED,
            'starts_at' => Carbon::parse($validated['broadcastStartsAt']),
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
            'broadcastStartsAt',
        );

        $this->broadcastFromDomain = $this->allowedDomains[0] ?? '';
        $this->broadcastMessagesPerMinute = 1;
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

            <flux:button wire:click="openCreateBroadcastModal" variant="primary">
                {{ __('Create Broadcast') }}
            </flux:button>
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
                                    <td class="py-2 pe-3">{{ $broadcast->starts_at?->format('Y-m-d H:i') ?? '-' }}</td>
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
                                        <div class="flex flex-wrap gap-2">
                                            <flux:button wire:click="openDuplicateModal({{ $broadcast->id }})" size="sm" variant="ghost">
                                                <flux:icon name="document-duplicate" class="w-4 h-4" />
                                            </flux:button>
                                            <flux:button wire:click="openRecipientsModal({{ $broadcast->id }})" size="sm" variant="ghost">
                                                {{ __('Recipients') }}
                                            </flux:button>
                                            <flux:button :href="route('broadcasts.history', ['broadcast_id' => $broadcast->id])" wire:navigate size="sm" variant="ghost">
                                                {{ __('History') }}
                                            </flux:button>
                                            <flux:button wire:click="requeueBroadcast({{ $broadcast->id }})" size="sm" variant="outline">
                                                {{ __('Requeue') }}
                                            </flux:button>
                                            @if ($broadcast->status === Broadcast::STATUS_SCHEDULED)
                                                <flux:button wire:click="startBroadcast({{ $broadcast->id }})" size="sm" variant="primary">
                                                    {{ __('Start') }}
                                                </flux:button>
                                                <flux:button wire:click="cancelBroadcast({{ $broadcast->id }})" size="sm" variant="ghost">
                                                    {{ __('Cancel') }}
                                                </flux:button>
                                            @elseif ($broadcast->status === Broadcast::STATUS_RUNNING)
                                                <flux:button wire:click="pauseBroadcast({{ $broadcast->id }})" size="sm" variant="ghost">
                                                    {{ __('Pause') }}
                                                </flux:button>
                                            @elseif ($broadcast->status === Broadcast::STATUS_PAUSED)
                                                <flux:button wire:click="resumeBroadcast({{ $broadcast->id }})" size="sm" variant="primary">
                                                    {{ __('Resume') }}
                                                </flux:button>
                                                <flux:button wire:click="cancelBroadcast({{ $broadcast->id }})" size="sm" variant="ghost">
                                                    {{ __('Cancel') }}
                                                </flux:button>
                                            @endif
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

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="broadcastMessagesPerMinute" :label="__('Messages Per Minute')" type="number" min="1" required />
                    <flux:input wire:model="broadcastStartsAt" :label="__('Starts At')" type="datetime-local" required />
                </div>

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
</section>
