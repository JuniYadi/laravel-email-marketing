<?php

use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    use WithFileUploads;

    // Group properties
    public string $groupName = '';

    // Import properties
    public array $selectedGroupIds = [];
    public mixed $csvFile = null;
    public int $importedContactsCount = 0;

    // Create contact properties
    public string $contactEmail = '';
    public string $contactFirstName = '';
    public string $contactLastName = '';
    public string $contactCompany = '';
    public array $contactGroupIds = [];

    // Edit contact properties
    public ?int $editingContactId = null;
    public string $editContactEmail = '';
    public string $editContactFirstName = '';
    public string $editContactLastName = '';
    public string $editContactCompany = '';
    public array $editContactGroupIds = [];

    // Delete contact properties
    public ?int $deletingContactId = null;

    // Modal state
    public bool $showCreateGroupModal = false;
    public bool $showImportModal = false;
    public bool $showCreateContactModal = false;
    public bool $showEditContactModal = false;
    public bool $showDeleteContactModal = false;

    // Tab state
    public string $activeTab = 'contacts';

    /**
     * Open create group modal.
     */
    public function openCreateGroupModal(): void
    {
        $this->showCreateGroupModal = true;
    }

    /**
     * Open import contacts modal.
     */
    public function openImportModal(): void
    {
        $this->showImportModal = true;
    }

    /**
     * Open create contact modal.
     */
    public function openCreateContactModal(): void
    {
        $this->showCreateContactModal = true;
    }

    /**
     * Open edit contact modal.
     */
    public function openEditContactModal(int $contactId): void
    {
        $contact = Contact::query()->with('groups')->findOrFail($contactId);

        $this->editingContactId = $contact->id;
        $this->editContactEmail = $contact->email;
        $this->editContactFirstName = $contact->first_name;
        $this->editContactLastName = $contact->last_name;
        $this->editContactCompany = $contact->company ?? '';
        $this->editContactGroupIds = $contact->groups->pluck('id')->toArray();
        $this->showEditContactModal = true;
    }

    /**
     * Open delete contact modal.
     */
    public function openDeleteContactModal(int $contactId): void
    {
        $this->deletingContactId = $contactId;
        $this->showDeleteContactModal = true;
    }

    /**
     * Create a new contact group.
     */
    public function createGroup(): void
    {
        $validated = $this->validate([
            'groupName' => ['required', 'string', 'max:255'],
        ]);

        ContactGroup::query()->create([
            'name' => $validated['groupName'],
        ]);

        $this->reset('groupName');
        $this->showCreateGroupModal = false;

        $this->dispatch('contact-group-created');
    }

    /**
     * Create a new contact.
     */
    public function createContact(): void
    {
        $validated = $this->validate([
            'contactEmail' => ['required', 'email', 'unique:contacts,email'],
            'contactFirstName' => ['required', 'string', 'max:255'],
            'contactLastName' => ['required', 'string', 'max:255'],
            'contactCompany' => ['nullable', 'string', 'max:255'],
            'contactGroupIds' => ['array'],
            'contactGroupIds.*' => ['integer', 'exists:contact_groups,id'],
        ]);

        $contact = Contact::query()->create([
            'email' => $validated['contactEmail'],
            'first_name' => $validated['contactFirstName'],
            'last_name' => $validated['contactLastName'],
            'company' => $validated['contactCompany'] ?? null,
        ]);

        if (! empty($validated['contactGroupIds'])) {
            $contact->groups()->sync($validated['contactGroupIds']);
        }

        $this->reset('contactEmail', 'contactFirstName', 'contactLastName', 'contactCompany', 'contactGroupIds');
        $this->showCreateContactModal = false;
        $this->dispatch('contact-created');
    }

    /**
     * Update an existing contact.
     */
    public function updateContact(): void
    {
        $validated = $this->validate([
            'editContactEmail' => ['required', 'email', 'unique:contacts,email,'.$this->editingContactId],
            'editContactFirstName' => ['required', 'string', 'max:255'],
            'editContactLastName' => ['required', 'string', 'max:255'],
            'editContactCompany' => ['nullable', 'string', 'max:255'],
            'editContactGroupIds' => ['array'],
            'editContactGroupIds.*' => ['integer', 'exists:contact_groups,id'],
        ]);

        $contact = Contact::query()->findOrFail($this->editingContactId);

        $contact->update([
            'email' => $validated['editContactEmail'],
            'first_name' => $validated['editContactFirstName'],
            'last_name' => $validated['editContactLastName'],
            'company' => $validated['editContactCompany'] ?? null,
        ]);

        $contact->groups()->sync($validated['editContactGroupIds'] ?? []);

        $this->reset('editingContactId', 'editContactEmail', 'editContactFirstName', 'editContactLastName', 'editContactCompany', 'editContactGroupIds');
        $this->showEditContactModal = false;
        $this->dispatch('contact-updated');
    }

    /**
     * Delete a contact.
     */
    public function deleteContact(): void
    {
        Contact::query()->findOrFail($this->deletingContactId)->delete();

        $this->deletingContactId = null;
        $this->showDeleteContactModal = false;
        $this->dispatch('contact-deleted');
    }

    /**
     * Import contacts from CSV file and assign selected groups.
     */
    public function importContacts(): void
    {
        $validated = $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'selectedGroupIds' => ['array'],
            'selectedGroupIds.*' => ['integer', 'exists:contact_groups,id'],
        ]);

        $rows = array_map('str_getcsv', file($validated['csvFile']->getRealPath()));

        if ($rows === [] || ! isset($rows[0])) {
            return;
        }

        $headers = collect($rows[0])
            ->map(fn (string $header): string => trim($header))
            ->all();

        $importedCount = 0;

        foreach (array_slice($rows, 1) as $row) {
            if ($row === [] || count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rowData = $this->buildRowData($headers, $row);
            $email = trim((string) ($rowData['email'] ?? ''));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            [$firstName, $lastName] = $this->extractNames($rowData);

            if ($firstName === '') {
                continue;
            }

            $contact = Contact::query()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'company' => $this->nullableTrim($rowData['company'] ?? null),
                    'is_invalid' => $this->toBoolean($rowData['isinvalid'] ?? null),
                ],
            );

            $csvGroupIds = $this->extractCsvGroupIds($rowData);
            $groupIds = collect($validated['selectedGroupIds'])
                ->merge($csvGroupIds)
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($groupIds !== []) {
                $existingGroupIds = ContactGroup::query()->whereIn('id', $groupIds)->pluck('id');
                $contact->groups()->syncWithoutDetaching($existingGroupIds);
            }

            $importedCount++;
        }

        $this->importedContactsCount = $importedCount;
        $this->reset('csvFile');
        $this->showImportModal = false;
        $this->dispatch('contacts-imported');
    }

    /**
     * Map CSV row values to normalized keys.
     *
     * @param  list<string>  $headers
     * @param  list<string>  $row
     * @return array<string, string>
     */
    protected function buildRowData(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            $normalizedHeader = Str::lower(str_replace([' ', '_'], '', $header));
            $mapped[$normalizedHeader] = trim((string) ($row[$index] ?? ''));
        }

        return $mapped;
    }

    /**
     * Resolve first and last name from CSV fields.
     *
     * @param  array<string, string>  $rowData
     * @return array{0: string, 1: string}
     */
    protected function extractNames(array $rowData): array
    {
        $firstName = trim((string) ($rowData['firstname'] ?? ''));
        $lastName = trim((string) ($rowData['lastname'] ?? ''));

        if ($firstName !== '') {
            return [$firstName, $lastName];
        }

        $fullName = trim((string) ($rowData['fullname'] ?? ''));

        if ($fullName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        $firstName = (string) array_shift($parts);
        $lastName = trim(implode(' ', $parts));

        return [$firstName, $lastName];
    }

    /**
     * Convert CSV value to boolean.
     */
    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    /**
     * Normalize nullable text.
     */
    protected function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Parse group ids from CSV groups column.
     *
     * @param  array<string, string>  $rowData
     * @return list<int>
     */
    protected function extractCsvGroupIds(array $rowData): array
    {
        $groups = trim((string) ($rowData['groups'] ?? ''));

        if ($groups === '') {
            return [];
        }

        return collect(explode(',', $groups))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * Export all contacts with assigned groups to CSV.
     */
    public function exportContactsCsv(): StreamedResponse
    {
        $filename = sprintf('contacts-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'id',
                'email',
                'first_name',
                'last_name',
                'company',
                'is_invalid',
                'group_ids',
                'group_names',
                'created_at',
            ], ',', '"', '');

            Contact::query()
                ->with('groups:id,name')
                ->orderBy('id')
                ->chunkById(500, function (Collection $contacts) use ($handle): void {
                    foreach ($contacts as $contact) {
                        fputcsv($handle, [
                            $contact->id,
                            $contact->email,
                            $contact->first_name,
                            $contact->last_name,
                            $contact->company,
                            $contact->is_invalid ? '1' : '0',
                            $contact->groups->pluck('id')->implode('|'),
                            $contact->groups->pluck('name')->implode('|'),
                            $contact->created_at?->format('Y-m-d H:i:s'),
                        ], ',', '"', '');
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export all groups with contact counts to CSV.
     */
    public function exportGroupsCsv(): StreamedResponse
    {
        $filename = sprintf('contact-groups-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'id',
                'name',
                'contacts_count',
                'created_at',
            ], ',', '"', '');

            ContactGroup::query()
                ->withCount('contacts')
                ->orderBy('id')
                ->chunkById(500, function (Collection $groups) use ($handle): void {
                    foreach ($groups as $group) {
                        fputcsv($handle, [
                            $group->id,
                            $group->name,
                            $group->contacts_count,
                            $group->created_at?->format('Y-m-d H:i:s'),
                        ], ',', '"', '');
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    #[Computed]
    public function groups(): Collection
    {
        return ContactGroup::query()->with('contacts')->orderBy('id')->get();
    }

    #[Computed]
    public function contacts(): Collection
    {
        return Contact::query()
            ->with('groups')
            ->latest()
            ->limit(25)
            ->get();
    }

    #[Computed]
    public function contactCount(): int
    {
        return Contact::query()->count();
    }
}; ?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Contacts') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Create reusable contact groups and import contacts from CSV.') }}</flux:text>
            </div>

            @if ($activeTab === 'contacts')
                <div class="flex flex-wrap items-center gap-3">
                    <flux:button wire:click="exportContactsCsv" variant="outline" icon="arrow-down-tray">
                        {{ __('Export Contacts') }}
                    </flux:button>
                    <flux:button wire:click="exportGroupsCsv" variant="outline" icon="arrow-down-tray">
                        {{ __('Export Groups') }}
                    </flux:button>
                    <flux:button wire:click="openCreateGroupModal" variant="primary">
                        {{ __('Create Group') }}
                    </flux:button>
                    <flux:button wire:click="openImportModal" variant="filled">
                        {{ __('Import CSV') }}
                    </flux:button>
                    <flux:button wire:click="openCreateContactModal" variant="outline">
                        {{ __('Add Contact') }}
                    </flux:button>
                </div>
            @endif
        </div>

        <!-- Tabs Navigation -->
        <div class="flex items-center gap-4 border-b border-zinc-200 dark:border-zinc-700">
            <button
                wire:click="$set('activeTab', 'contacts')"
                @class($activeTab === 'contacts' ? 'border-b-2 border-zinc-900 pb-2 font-medium' : 'pb-2 text-zinc-500 hover:text-zinc-900')"
            >
                {{ __('Contacts') }}
            </button>
            <button
                wire:click="$set('activeTab', 'groups')"
                @class($activeTab === 'groups' ? 'border-b-2 border-zinc-900 pb-2 font-medium' : 'pb-2 text-zinc-500 hover:text-zinc-900')"
            >
                {{ __('Groups') }}
            </button>
        </div>

        @if ($activeTab === 'contacts')
            <!-- Stats cards section -->
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <flux:text>{{ __('Total Groups') }}</flux:text>
                        <flux:heading size="lg">{{ $this->groups->count() }}</flux:heading>
                    </div>
                    <div>
                        <flux:text>{{ __('Imported Contacts') }}</flux:text>
                        <flux:heading size="lg">{{ $this->contactCount }}</flux:heading>
                    </div>
                    <div>
                        <flux:text>{{ __('Last Import') }}</flux:text>
                        <flux:heading size="lg">{{ $importedContactsCount }}</flux:heading>
                    </div>
                </div>
            </div>

            <!-- Recent Contacts table -->
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading>{{ __('Recent Contacts') }}</flux:heading>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="py-2 pe-3">{{ __('Email') }}</th>
                                <th class="py-2 pe-3">{{ __('Full Name') }}</th>
                                <th class="py-2 pe-3">{{ __('Company') }}</th>
                                <th class="py-2 pe-3">{{ __('Invalid') }}</th>
                                <th class="py-2 pe-3">{{ __('Groups') }}</th>
                                <th class="py-2">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->contacts as $contact)
                                <tr wire:key="contact-row-{{ $contact->id }}" class="border-b border-zinc-100 align-top dark:border-zinc-800">
                                    <td class="py-2 pe-3">{{ $contact->email }}</td>
                                    <td class="py-2 pe-3">{{ $contact->full_name }}</td>
                                    <td class="py-2 pe-3">{{ $contact->company ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $contact->is_invalid ? __('Yes') : __('No') }}</td>
                                    <td class="py-2 pe-3">
                                        <div class="flex flex-wrap gap-2">
                                            @forelse ($contact->groups as $group)
                                                <flux:badge wire:key="contact-{{ $contact->id }}-group-{{ $group->id }}" size="sm">{{ $group->name }}</flux:badge>
                                            @empty
                                                <flux:text>-</flux:text>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="py-2">
                                        <div class="flex flex-wrap gap-2">
                                            <flux:button wire:click="openEditContactModal({{ $contact->id }})" size="sm" variant="ghost">
                                                {{ __('Edit') }}
                                            </flux:button>
                                            <flux:button wire:click="openDeleteContactModal({{ $contact->id }})" size="sm" variant="danger">
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-4" colspan="6">
                                        <flux:text>{{ __('No contacts yet. Import a CSV or add your first contact.') }}</flux:text>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <!-- Groups Tab -->
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="flex items-center justify-between">
                    <flux:heading>{{ __('Groups') }}</flux:heading>
                    <flux:button wire:click="openCreateGroupModal" size="sm" variant="ghost">
                        {{ __('Create New') }}
                    </flux:button>
                </div>

                @if ($this->groups->isNotEmpty())
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                    <th class="py-2 pe-3">{{ __('Name') }}</th>
                                    <th class="py-2 pe-3">{{ __('Contacts') }}</th>
                                    <th class="py-2">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->groups as $group)
                                    <tr wire:key="group-row-{{ $group->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
                                        <td class="py-2 pe-3">
                                            <flux:link :href="route('contacts.groups.show', $group)" wire:navigate>
                                                {{ $group->name }}
                                            </flux:link>
                                        </td>
                                        <td class="py-2 pe-3">{{ $group->contacts->count() }}</td>
                                        <td class="py-2">
                                            <flux:button :href="route('contacts.groups.show', $group)" size="sm" variant="ghost" wire:navigate>
                                                {{ __('View') }}
                                            </flux:button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <flux:text class="mt-4">{{ __('No groups yet. Create your first group to get started.') }}</flux:text>
                @endif
            </div>
        @endif
    </div>

    <!-- Create Group Modal -->
    <flux:modal wire:model="showCreateGroupModal" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading>{{ __('Create Group') }}</flux:heading>

            <form wire:submit="createGroup" class="space-y-4">
                <flux:input wire:model="groupName" :label="__('Name')" type="text" required autofocus />

                <div class="flex items-center justify-end gap-2">
                    <flux:button wire:click="$set('showCreateGroupModal', false)" variant="ghost" type="button">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ __('Save Group') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Import CSV Modal -->
    <flux:modal wire:model="showImportModal" class="max-w-2xl">
        <div class="space-y-4">
            <flux:heading>{{ __('Import CSV') }}</flux:heading>
            <flux:text>{{ __('Headers: email, firstName, lastName, fullName, company, isInvalid, groups') }}</flux:text>

            <form wire:submit="importContacts" class="space-y-4">
                <flux:input wire:model="csvFile" type="file" :label="__('CSV File')" />

                @if ($this->groups->isNotEmpty())
                    <div class="space-y-2">
                        <flux:text>{{ __('Assign imported contacts to groups (optional)') }}</flux:text>
                        @foreach ($this->groups as $group)
                            <flux:checkbox
                                wire:key="import-group-{{ $group->id }}"
                                wire:model="selectedGroupIds"
                                value="{{ $group->id }}"
                                :label="$group->name"
                            />
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-end gap-2">
                    <flux:button wire:click="$set('showImportModal', false)" variant="ghost" type="button">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ __('Import Contacts') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Create Contact Modal -->
    <flux:modal wire:model="showCreateContactModal" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading>{{ __('Add Contact') }}</flux:heading>

            <form wire:submit="createContact" class="space-y-4">
                <flux:input wire:model="contactEmail" :label="__('Email')" type="email" required autofocus />
                <flux:input wire:model="contactFirstName" :label="__('First Name')" type="text" required />
                <flux:input wire:model="contactLastName" :label="__('Last Name')" type="text" required />
                <flux:input wire:model="contactCompany" :label="__('Company')" type="text" />

                @if ($this->groups->isNotEmpty())
                    <div class="space-y-2">
                        <flux:text>{{ __('Assign to groups (optional)') }}</flux:text>
                        @foreach ($this->groups as $group)
                            <flux:checkbox
                                wire:key="contact-group-{{ $group->id }}"
                                wire:model="contactGroupIds"
                                value="{{ $group->id }}"
                                :label="$group->name"
                            />
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-end gap-2">
                    <flux:button wire:click="$set('showCreateContactModal', false)" variant="ghost" type="button">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ __('Save Contact') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Edit Contact Modal -->
    <flux:modal wire:model="showEditContactModal" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading>{{ __('Edit Contact') }}</flux:heading>

            <form wire:submit="updateContact" class="space-y-4">
                <flux:input wire:model="editContactEmail" :label="__('Email')" type="email" required autofocus />
                <flux:input wire:model="editContactFirstName" :label="__('First Name')" type="text" required />
                <flux:input wire:model="editContactLastName" :label="__('Last Name')" type="text" required />
                <flux:input wire:model="editContactCompany" :label="__('Company')" type="text" />

                @if ($this->groups->isNotEmpty())
                    <div class="space-y-2">
                        <flux:text>{{ __('Assign to groups (optional)') }}</flux:text>
                        @foreach ($this->groups as $group)
                            <flux:checkbox
                                wire:key="edit-contact-group-{{ $group->id }}"
                                wire:model="editContactGroupIds"
                                value="{{ $group->id }}"
                                :label="$group->name"
                            />
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-end gap-2">
                    <flux:button wire:click="$set('showEditContactModal', false)" variant="ghost" type="button">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ __('Update Contact') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Delete Contact Modal -->
    <flux:modal wire:model="showDeleteContactModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading>{{ __('Delete Contact') }}</flux:heading>
            <flux:text>{{ __('Are you sure you want to delete this contact? This action cannot be undone.') }}</flux:text>

            <div class="flex items-center justify-end gap-2">
                <flux:button wire:click="$set('showDeleteContactModal', false)" variant="ghost" type="button">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="deleteContact" variant="danger" type="button">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
