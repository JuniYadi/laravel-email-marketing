<?php

use Livewire\Component;

new class extends Component {
}; ?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8">
        <div>
            <flux:heading size="xl">{{ __('Gallery') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Upload and manage public media assets for HTML email embeds.') }}</flux:text>
        </div>
    </div>
</section>
