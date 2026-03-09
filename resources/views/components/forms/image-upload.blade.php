@props([
    'label',
    'urlModel',
    'uploadModel',
    'currentUrl' => '',
    'required' => false,
    'helpText' => null,
])

<div class="space-y-3">
    <flux:input wire:model="{{ $urlModel }}" :label="$label" type="url" :required="$required" />

    <flux:field>
        <flux:label>{{ __('Upload Image') }}</flux:label>
        <input
            wire:model="{{ $uploadModel }}"
            type="file"
            accept="image/*"
            class="block w-full cursor-pointer rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:file:bg-zinc-800 dark:hover:file:bg-zinc-700"
        >
    </flux:field>

    @if (is_string($currentUrl) && $currentUrl !== '')
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950">
            <img src="{{ $currentUrl }}" alt="{{ $label }}" class="h-40 w-full object-cover">
        </div>

        <div class="flex items-center justify-end">
            <flux:button type="button" variant="ghost" size="sm" x-on:click.prevent="$wire.set('{{ $urlModel }}', '')">
                {{ __('Clear Image') }}
            </flux:button>
        </div>
    @endif

    @if (is_string($helpText) && $helpText !== '')
        <flux:text class="text-xs text-zinc-500">{{ $helpText }}</flux:text>
    @endif
</div>
