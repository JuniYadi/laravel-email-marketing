<?php

use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    // Route model binding
    public ContactGroup $group;

    // Search/filter
    public string $search = '';

    // Pagination
    public int $perPage = 25;

    // Edit contact modal state
    public ?int $editingContactId = null;
    public string $editContactEmail = '';
    public string $editContactFirstName = '';
    public string $editContactLastName = '';
    public string $editContactCompany = '';
    public array $editContactGroupIds = [];
    public bool $showEditContactModal = false;

    // Delete contact modal state
    public bool $showDeleteContactModal = false;
    public ?int $deletingContactId = null;

    // Add contact modal state
    public string $contactEmail = '';
    public string $contactFirstName = '';
    public string $contactLastName = '';
    public string $contactCompany = '';
    public array $contactGroupIds = [];
    public bool $showCreateContactModal = false;

    /**
     * Mount the component with the group.
     */
    public function mount(ContactGroup $group): void
    {
        $this->group = $group->load(['contacts']);
        $this->contactGroupIds = [$group->id];
        $this->editContactGroupIds = [$group->id];
        $this->perPage = 25;
    }

    /**
     * Open create contact modal.
     */
    public function openCreateContactModal(): void
    {
        $this->showCreateContactModal = true;
        $this->contactGroupIds = [$this->group->id];
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
        $this->contactGroupIds = [$this->group->id];
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
        $this->editContactGroupIds = [$this->group->id];
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
     * Get paginated contacts for this group.
     */
    #[Computed]
    public function contacts(): LengthAwarePaginator
    {
        return $this->group->contacts()
            ->when($this->search !== '', fn ($q) => $q->where('email', 'like', '%'.$this->search.'%'))
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);
    }

    /**
     * Get total contact count for this group.
     */
    #[Computed]
    public function contactCount(): int
    {
        return $this->group->contacts()->count();
    }

    /**
     * Get all available groups for the modal checkboxes.
     */
    #[Computed]
    public function allGroups(): Collection
    {
        return ContactGroup::query()->orderBy('name')->get();
    }
}
?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8">
        <!-- Header with back button -->
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ $group->name }}</flux:heading>
            <flux:button :href="route('contacts.index')" variant="ghost" icon="arrow-left" wire:navigate>
                {{ __('Back to Contacts') }}
            </flux:button>
        </div>

        <!-- Group Details Card -->
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Group Details') }}</flux:heading>
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <flux:text>{{ __('Name') }}</flux:text>
                    <flux:heading size="md">{{ $group->name }}</flux:heading>
                </div>
                <div>
                    <flux:text>{{ __('Reply To') }}</flux:text>
                    <flux:heading size="md">{{ $group->reply_to }}</flux:heading>
                </div>
                <div>
                    <flux:text>{{ __('From Prefix') }}</flux:text>
                    <flux:heading size="md">{{ $group->from_email_prefix }}</flux:heading>
                </div>
                <div>
                    <flux:text>{{ __('Total Contacts') }}</flux:text>
                    <flux:heading size="md">{{ $this->contactCount }}</flux:heading>
                </div>
                <div>
                    <flux:text>{{ __('Template') }}</flux:text>
                    <flux:heading size="md">{{ $group->template_id }}</flux:heading>
                </div>
                <div>
                    <flux:text>{{ __('Rate') }}</flux:text>
                    <flux:heading size="md">{{ $group->message_per_minutes }}/min</flux:heading>
                </div>
                <div>
                    <flux:text>{{ __('Status') }}</flux:text>
                    @if ($group->start_broadcast)
                        <flux:badge color="emerald" size="md">{{ __('Active') }}</flux:badge>
                    @else
                        <flux:badge size="md">{{ __('Paused') }}</flux:badge>
                    @endif
                </div>
            </div>
        </div>

        <!-- Search & Add Contact -->
        <div class="flex items-center justify-between gap-4">
            <flux:input wire:model.live="search" placeholder="{{ __('Search contacts by email...') }}" type="search" class="flex-1" />
            <flux:button wire:click="openCreateContactModal" variant="primary">
                {{ __('Add Contact') }}
            </flux:button>
        </div>

        <!-- Contacts Table -->
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading>{{ __('Contacts') }}</flux:heading>

            @if ($this->contacts->count() > 0)
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="py-2 pe-3">{{ __('Email') }}</th>
                                <th class="py-2 pe-3">{{ __('Full Name') }}</th>
                                <th class="py-2 pe-3">{{ __('Company') }}</th>
                                <th class="py-2 pe-3">{{ __('Invalid') }}</th>
                                <th class="py-2">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->contacts as $contact)
                                <tr wire:key="contact-row-{{ $contact->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
                                    <td class="py-2 pe-3">{{ $contact->email }}</td>
                                    <td class="py-2 pe-3">{{ $contact->full_name }}</td>
                                    <td class="py-2 pe-3">{{ $contact->company ?? '-' }}</td>
                                    <td class="py-2 pe-3">{{ $contact->is_invalid ? __('Yes') : __('No') }}</td>
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
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <flux:pagination :paginator="$this->contacts" />
                </div>
            @else
                <div class="py-8 text-center">
                    <flux:text>
                        @if ($search !== '')
                            {{ __('No contacts found matching your search.') }}
                        @else
                            {{ __('No contacts in this group yet.') }}
                        @endif
                    </flux:text>
                </div>
            @endif
        </div>
    </div>

    <!-- Create Contact Modal -->
    <flux:modal wire:model="showCreateContactModal" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading>{{ __('Add Contact') }}</flux:heading>

            <form wire:submit="createContact" class="space-y-4">
                <flux:input wire:model="contactEmail" :label="__('Email')" type="email" required autofocus />
                <flux:input wire:model="contactFirstName" :label="__('First Name')" type="text" required />
                <flux:input wire:model="contactLastName" :label="__('Last Name')" type="text" required />
                <flux:input wire:model="contactCompany" :label="__('Company')" type="text" />

                @if ($this->allGroups->isNotEmpty())
                    <div class="space-y-2">
                        <flux:text>{{ __('Assign to groups (optional)') }}</flux:text>
                        @foreach ($this->allGroups as $availableGroup)
                            <flux:checkbox
                                wire:key="contact-group-{{ $availableGroup->id }}"
                                wire:model="contactGroupIds"
                                value="{{ $availableGroup->id }}"
                                :label="$availableGroup->name"
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

                @if ($this->allGroups->isNotEmpty())
                    <div class="space-y-2">
                        <flux:text>{{ __('Assign to groups (optional)') }}</flux:text>
                        @foreach ($this->allGroups as $availableGroup)
                            <flux:checkbox
                                wire:key="edit-contact-group-{{ $availableGroup->id }}"
                                wire:model="editContactGroupIds"
                                value="{{ $availableGroup->id }}"
                                :label="$availableGroup->name"
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
