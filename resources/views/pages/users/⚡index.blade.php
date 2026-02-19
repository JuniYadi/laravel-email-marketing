<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $editingUserId = null;
    public string $editUserName = '';
    public string $editUserEmail = '';
    public string $editUserPassword = '';
    public string $editUserPasswordConfirmation = '';
    public bool $editUserIsAdmin = false;

    public ?int $deletingUserId = null;

    public bool $showEditUserModal = false;
    public bool $showDeleteUserModal = false;

    /**
     * Ensure only admins can access this component.
     */
    public function mount(): void
    {
        Gate::authorize('manage-users');
    }

    /**
     * Open edit user modal.
     */
    public function openEditUserModal(int $userId): void
    {
        Gate::authorize('manage-users');

        $user = User::query()->findOrFail($userId);

        $this->editingUserId = $user->id;
        $this->editUserName = $user->name;
        $this->editUserEmail = $user->email;
        $this->editUserPassword = '';
        $this->editUserPasswordConfirmation = '';
        $this->editUserIsAdmin = $user->is_admin;
        $this->showEditUserModal = true;
    }

    /**
     * Update a user.
     */
    public function updateUser(): void
    {
        Gate::authorize('manage-users');

        $user = User::query()->findOrFail($this->editingUserId);

        if ($user->is_admin && ! $this->editUserIsAdmin && User::query()->where('is_admin', true)->count() <= 1) {
            abort(403);
        }

        $validated = $this->validate([
            'editUserName' => ['required', 'string', 'max:255'],
            'editUserEmail' => ['required', 'email', Rule::unique(User::class, 'email')->ignore($user->id)],
            'editUserPassword' => ['nullable', 'string', 'min:8', 'same:editUserPasswordConfirmation'],
            'editUserPasswordConfirmation' => ['nullable', 'string', 'min:8'],
            'editUserIsAdmin' => ['boolean'],
        ]);

        $updatePayload = [
            'name' => $validated['editUserName'],
            'email' => $validated['editUserEmail'],
            'is_admin' => $validated['editUserIsAdmin'],
        ];

        if (filled($validated['editUserPassword'])) {
            $updatePayload['password'] = $validated['editUserPassword'];
        }

        $user->update($updatePayload);

        $this->reset([
            'editingUserId',
            'editUserName',
            'editUserEmail',
            'editUserPassword',
            'editUserPasswordConfirmation',
            'editUserIsAdmin',
        ]);

        $this->showEditUserModal = false;
        $this->dispatch('user-updated');
    }

    /**
     * Open delete user modal.
     */
    public function openDeleteUserModal(int $userId): void
    {
        Gate::authorize('manage-users');

        $this->deletingUserId = $userId;
        $this->showDeleteUserModal = true;
    }

    /**
     * Delete a user.
     */
    public function deleteUser(): void
    {
        Gate::authorize('manage-users');

        $user = User::query()->findOrFail($this->deletingUserId);

        if (Auth::id() === $user->id) {
            abort(403);
        }

        if ($user->is_admin && User::query()->where('is_admin', true)->count() <= 1) {
            abort(403);
        }

        $user->delete();

        $this->reset('deletingUserId');
        $this->showDeleteUserModal = false;
        $this->dispatch('user-deleted');
    }

    #[Computed]
    public function users(): Collection
    {
        return User::query()->latest()->get();
    }
}; ?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8">
        <div>
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Manage registered users and administrator access.') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="py-2 pe-3">{{ __('Name') }}</th>
                            <th class="py-2 pe-3">{{ __('Email') }}</th>
                            <th class="py-2 pe-3">{{ __('Admin') }}</th>
                            <th class="py-2 pe-3">{{ __('Created') }}</th>
                            <th class="py-2">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->users as $user)
                            <tr wire:key="user-row-{{ $user->id }}" class="border-b border-zinc-100 align-top dark:border-zinc-800">
                                <td class="py-2 pe-3">{{ $user->name }}</td>
                                <td class="py-2 pe-3">{{ $user->email }}</td>
                                <td class="py-2 pe-3">{{ $user->is_admin ? __('Yes') : __('No') }}</td>
                                <td class="py-2 pe-3">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button wire:click="openEditUserModal({{ $user->id }})" size="sm" variant="ghost">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button wire:click="openDeleteUserModal({{ $user->id }})" size="sm" variant="danger">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-4" colspan="5">
                                    <flux:text>{{ __('No users found.') }}</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <flux:modal wire:model="showEditUserModal" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit User') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Update user details and permissions.') }}</flux:text>
            </div>

            <div class="grid gap-4">
                <flux:input wire:model="editUserName" :label="__('Name')" type="text" required autofocus />
                <flux:input wire:model="editUserEmail" :label="__('Email')" type="email" required />
                <flux:input wire:model="editUserPassword" :label="__('Password')" type="password" />
                <flux:input wire:model="editUserPasswordConfirmation" :label="__('Confirm Password')" type="password" />
                <flux:checkbox wire:model="editUserIsAdmin" :label="__('Administrator')" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showEditUserModal', false)" variant="ghost">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="updateUser" variant="primary">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDeleteUserModal" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Delete User') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone.') }}</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showDeleteUserModal', false)" variant="ghost">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="deleteUser" variant="danger">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
