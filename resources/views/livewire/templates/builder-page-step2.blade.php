<aside class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900 xl:sticky xl:top-6 xl:h-[calc(100vh-8rem)] xl:overflow-hidden">
    <div class="mb-4 flex items-center justify-between">
        <flux:button type="button" variant="ghost" size="sm" wire:click="backToSetup" icon="arrow-left">
            {{ __('Back to Setup') }}
        </flux:button>
    </div>

    <flux:radio.group wire:model.live="sidebarTab" variant="segmented" size="sm">
        <flux:radio value="starter" :label="__('Starter')" />
        <flux:radio value="rows" :label="__('Rows')" />
        <flux:radio value="elements" :label="__('Elements')" />
        <flux:radio value="settings" :label="__('Settings')" />
    </flux:radio.group>

    <div class="mt-4 space-y-4 xl:h-[calc(100%-5rem)] xl:overflow-y-auto xl:pr-1">
        @if ($sidebarTab === 'starter')
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Starter Templates') }}</flux:heading>

                <flux:radio.group wire:model.live="templateKey" :label="__('Template')">
                    @foreach ($starterTemplateOptions as $key => $label)
                        <flux:radio value="{{ $key }}" :label="__($label)" />
                    @endforeach
                </flux:radio.group>
            </div>
        @endif

        @if ($sidebarTab === 'rows')
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Rows') }}</flux:heading>
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

        @if ($sidebarTab === 'settings')
            <div class="space-y-4">
                <flux:heading size="sm">{{ __('Settings') }}</flux:heading>

                <flux:radio.group wire:model.live="mode" :label="__('Mode')" variant="segmented" size="sm">
                    <flux:radio value="visual" :label="__('Visual')" />
                    <flux:radio value="raw" :label="__('Raw HTML')" />
                </flux:radio.group>

                <flux:field>
                    <flux:label>{{ __('Active') }}</flux:label>
                    <flux:switch wire:model="isActive" />
                </flux:field>

                @if ($mode === 'visual')
                    <div class="space-y-3">
                        <flux:heading size="sm">{{ __('Theme') }}</flux:heading>
                        <flux:input wire:model="theme.content_width" :label="__('Content Width')" type="number" min="480" max="760" />
                        <flux:input wire:model="theme.font_family" :label="__('Font Family')" type="text" />
                        <div class="grid grid-cols-2 gap-3">
                            <flux:input wire:model="theme.background_color" :label="__('Background')" type="color" />
                            <flux:input wire:model="theme.surface_color" :label="__('Surface')" type="color" />
                            <flux:input wire:model="theme.text_color" :label="__('Text')" type="color" />
                            <flux:input wire:model="theme.link_color" :label="__('Link')" type="color" />
                            <flux:input wire:model="theme.button_bg_color" :label="__('Button BG')" type="color" />
                            <flux:input wire:model="theme.button_text_color" :label="__('Button Text')" type="color" />
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</aside>
