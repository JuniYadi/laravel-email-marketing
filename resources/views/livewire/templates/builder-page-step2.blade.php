<aside class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900 xl:sticky xl:top-6 xl:h-[calc(100vh-8rem)] xl:overflow-hidden">
    <div class="space-y-3 border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <flux:button type="button" variant="ghost" size="sm" wire:click="backToSetup" icon="arrow-left">
            {{ __('Back to Setup') }}
        </flux:button>

        <flux:text class="text-xs text-zinc-500">
            {{ __('Theme, name, and subject are edited in Setup.') }}
        </flux:text>

        <flux:radio.group wire:model.live="mode" :label="__('Mode')" variant="segmented" size="sm">
            <flux:radio value="visual" :label="__('Visual')" />
            <flux:radio value="raw" :label="__('Raw HTML')" />
        </flux:radio.group>

        <flux:field>
            <flux:label>{{ __('Active') }}</flux:label>
            <flux:switch wire:model="isActive" />
        </flux:field>
    </div>

    <div class="mt-4 space-y-4 xl:h-[calc(100%-13rem)] xl:overflow-y-auto xl:pr-1">
        <flux:radio.group wire:model.live="sidebarTab" variant="segmented" size="sm">
            <flux:radio value="layout" :label="__('Layout')" />
            <flux:radio value="elements" :label="__('Elements')" />
        </flux:radio.group>

        @if ($sidebarTab === 'layout')
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Layout') }}</flux:heading>
                <flux:text class="text-sm">{{ __('Drag row presets to canvas or click add.') }}</flux:text>

                @foreach ($rowPresets as $columnCount => $preset)
                    <div
                        wire:key="row-preset-{{ $columnCount }}"
                        draggable="true"
                        x-on:dragstart="dragItem = { kind: 'row-preset', columns: {{ $columnCount }} }"
                        class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <flux:text class="font-medium">{{ __($preset['label']) }}</flux:text>
                            <flux:button type="button" size="xs" variant="ghost" wire:click="addRowPreset({{ $columnCount }})">
                                {{ __('Add') }}
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($sidebarTab === 'elements')
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Elements') }}</flux:heading>
                <flux:text class="text-sm">{{ __('Drag elements into any column.') }}</flux:text>

                @foreach ($elementPalette as $type => $item)
                    <div
                        wire:key="palette-{{ $type }}"
                        draggable="true"
                        x-on:dragstart="dragItem = { kind: 'palette-element', type: '{{ $type }}' }"
                        class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <flux:text class="font-medium">{{ __($item['label']) }}</flux:text>
                        <flux:text class="mt-1 text-xs">{{ __($item['description']) }}</flux:text>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</aside>
