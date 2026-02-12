<?php

use App\Models\Contact;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::auth.card')] class extends Component
{
    #[Locked]
    public Contact $contact;

    public bool $unsubscribed = false;

    public bool $alreadyUnsubscribed = false;

    public function mount(Contact $contact): void
    {
        $this->contact = $contact;

        if ($contact->isUnsubscribed()) {
            $this->alreadyUnsubscribed = true;
        }
    }

    public function unsubscribe(): void
    {
        if ($this->contact->isUnsubscribed()) {
            $this->alreadyUnsubscribed = true;

            return;
        }

        $this->contact->update([
            'unsubscribed_at' => now(),
        ]);

        $this->unsubscribed = true;
    }
}; ?>

<div class="w-full">
    @if($unsubscribed)
        <flux:card class="text-center">
            <flux:icon name="check-circle" class="mx-auto mb-4 w-12 h-12 text-green-500" />
            <flux:heading size="lg">You've been unsubscribed</flux:heading>
            <flux:text class="mt-2">
                You will no longer receive marketing emails from us.
            </flux:text>
        </flux:card>
    @elseif($alreadyUnsubscribed)
        <flux:card class="text-center">
            <flux:icon name="information-circle" class="mx-auto mb-4 w-12 h-12 text-blue-500" />
            <flux:heading size="lg">Already unsubscribed</flux:heading>
            <flux:text class="mt-2">
                This email address is already unsubscribed from our mailing list.
            </flux:text>
        </flux:card>
    @else
        <div class="text-center mb-6">
            <flux:heading size="lg">Unsubscribe from our mailing list</flux:heading>
            <flux:text class="mt-2">
                Are you sure you want to unsubscribe <strong>{{ $contact->email }}</strong> from our marketing emails?
            </flux:text>
        </div>
        <div class="flex gap-3 justify-center">
            <flux:button wire:click="unsubscribe" variant="danger">
                Yes, unsubscribe me
            </flux:button>
            <flux:button href="/" variant="ghost">
                Cancel
            </flux:button>
        </div>
    @endif
</div>
