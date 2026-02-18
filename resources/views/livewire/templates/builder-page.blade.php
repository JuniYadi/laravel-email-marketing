<section class="w-full">
    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6 lg:p-8"
        x-data="{ dragItem: null }"
        @if($currentStep === 2)
            x-on:keydown.window.ctrl.z.prevent="$wire.undo()"
            x-on:keydown.window.meta.z.prevent="$wire.undo()"
            x-on:keydown.window.ctrl.shift.z.prevent="$wire.redo()"
            x-on:keydown.window.meta.shift.z.prevent="$wire.redo()"
        @endif
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <flux:heading size="xl">{{ $isEditing ? __('Edit Template') : __('Create Template') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('True drag-and-drop email canvas. Every element is optional and fully customizable.') }}
                </flux:text>
            </div>

            <div class="flex items-center gap-2">
                @if ($currentStep === 2)
                    <flux:button type="button" variant="ghost" wire:click="undo">{{ __('Undo') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="redo">{{ __('Redo') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="backToSetup">
                        {{ __('Back to Setup') }}
                    </flux:button>
                @else
                    <flux:button type="button" variant="ghost" wire:click="continueToBuilder">
                        {{ __('Go to Canvas') }}
                    </flux:button>
                @endif

                <flux:button type="button" wire:click="cancelEditing" variant="ghost" icon="arrow-left">
                    {{ __('Back to Templates') }}
                </flux:button>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col items-center justify-center text-center">
                <x-wizard.step-indicator :current-step="$currentStep" />
                <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $currentStep === 1 ? __('Set up template details before entering the canvas.') : __('Build your template layout and content.') }}
                </flux:text>
            </div>
        </div>

        <form wire:submit="saveTemplate">
            @if ($currentStep === 1)
                <div class="grid gap-6 xl:grid-cols-[1fr_320px]">
                    <div class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                        @include('livewire.templates.builder-page-step1')
                    </div>

                    <aside class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="sm">{{ __('Next: Canvas Builder') }}</flux:heading>
                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('After setup, you can arrange rows, drag elements, and edit content directly in the canvas.') }}
                        </flux:text>

                        <div class="mt-4 space-y-2">
                            <flux:text class="text-xs text-zinc-500">{{ __('Step 2 includes:') }}</flux:text>
                            <ul class="space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                                <li>{{ __('• Layout presets') }}</li>
                                <li>{{ __('• Elements palette') }}</li>
                                <li>{{ __('• Visual/Raw mode') }}</li>
                            </ul>
                        </div>
                    </aside>
                </div>
            @else
                <div class="grid gap-6 xl:grid-cols-[1fr_320px]">
                    <div class="space-y-5 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <flux:heading size="lg">{{ __('Workspace') }}</flux:heading>
                                <flux:text class="mt-1 text-sm">{{ __('Drag and reorder rows/elements. Toggle preview anytime.') }}</flux:text>
                            </div>

                            <flux:radio.group wire:model.live="workspaceTab" variant="segmented" size="sm">
                                <flux:radio value="builder" :label="__('Builder')" />
                                <flux:radio value="preview" :label="__('Preview')" />
                            </flux:radio.group>
                        </div>

                        @if ($isLegacyTemplate)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                {{ __('Legacy template opened in raw mode because structured schema was unavailable.') }}
                            </div>
                        @endif

                        @if ($workspaceTab === 'builder')
                            @if ($mode === 'raw')
                                <flux:textarea wire:model="htmlContent" :label="__('HTML Content')" rows="24" resize="vertical" required />
                            @else
                                <div class="space-y-3">
                                    <div
                                        class="rounded-md border border-dashed border-zinc-300 px-3 py-2 text-center text-xs text-zinc-500 dark:border-zinc-600"
                                        x-on:dragover.prevent
                                        x-on:drop.prevent="
                                            if (!dragItem) return;
                                            if (dragItem.kind === 'row-preset') { $wire.addRowPresetAt(dragItem.columns, 0); }
                                            if (dragItem.kind === 'row') { $wire.moveRow(dragItem.rowId, 0); }
                                            dragItem = null;
                                        "
                                    >
                                        {{ __('Drop row preset here to insert at top') }}
                                    </div>

                                    @if ($rows === [])
                                        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-10 text-center dark:border-zinc-600 dark:bg-zinc-900">
                                            <flux:heading size="md">{{ __('Empty canvas') }}</flux:heading>
                                            <flux:text class="mt-2">{{ __('Add a row preset, then drop elements inside columns.') }}</flux:text>
                                        </div>
                                    @else
                                        @foreach ($rows as $rowIndex => $row)
                                            <div
                                                wire:key="row-{{ $row['id'] }}"
                                                draggable="true"
                                                x-on:dragstart="dragItem = { kind: 'row', rowId: '{{ $row['id'] }}' }"
                                                @class([
                                                    'rounded-xl border bg-white p-4 dark:bg-zinc-900',
                                                    'border-zinc-200 dark:border-zinc-700' => $selectedRowId !== $row['id'],
                                                    'border-sky-400 ring-2 ring-sky-200 dark:border-sky-500 dark:ring-sky-900' => $selectedRowId === $row['id'],
                                                ])
                                                wire:click.self="selectRow('{{ $row['id'] }}')"
                                            >
                                                <div class="mb-3 flex items-center justify-between gap-2">
                                                    <flux:text class="font-medium">{{ __('Row') }} #{{ $rowIndex + 1 }}</flux:text>
                                                    <div class="flex items-center gap-2">
                                                        <flux:text class="text-xs">{{ count($row['columns']) }} {{ __('columns') }}</flux:text>
                                                        <flux:button type="button" size="xs" variant="ghost" wire:click.stop="removeRow('{{ $row['id'] }}')">
                                                            {{ __('Remove Row') }}
                                                        </flux:button>
                                                    </div>
                                                </div>

                                                <div @class([
                                                    'grid gap-3',
                                                    'md:grid-cols-1' => count($row['columns']) === 1,
                                                    'md:grid-cols-2' => count($row['columns']) === 2,
                                                ])>
                                                    @foreach ($row['columns'] as $columnIndex => $column)
                                                        <div
                                                            wire:key="column-{{ $column['id'] }}"
                                                            @class([
                                                                'rounded-lg border p-3',
                                                                'border-zinc-200 dark:border-zinc-700' => $selectedColumnId !== $column['id'],
                                                                'border-sky-400 ring-2 ring-sky-200 dark:border-sky-500 dark:ring-sky-900' => $selectedColumnId === $column['id'],
                                                            ])
                                                            wire:click.stop="selectColumn('{{ $row['id'] }}', '{{ $column['id'] }}')"
                                                        >
                                                            <div class="mb-2 flex items-center justify-between">
                                                                <flux:text class="text-xs">{{ __('Column') }} {{ $column['width'] }}</flux:text>
                                                            </div>

                                                            <div
                                                                class="mb-2 rounded-md border border-dashed border-zinc-300 px-2 py-1 text-center text-xs text-zinc-500 dark:border-zinc-600"
                                                                x-on:dragover.prevent
                                                                x-on:drop.prevent="
                                                                    if (!dragItem) return;
                                                                    if (dragItem.kind === 'palette-element') { $wire.addElement('{{ $row['id'] }}', '{{ $column['id'] }}', dragItem.type, 0); }
                                                                    if (dragItem.kind === 'element') { $wire.moveElement(dragItem.elementId, '{{ $row['id'] }}', '{{ $column['id'] }}', 0); }
                                                                    dragItem = null;
                                                                "
                                                            >
                                                                {{ __('Drop element here') }}
                                                            </div>

                                                            @forelse ($column['elements'] as $elementIndex => $element)
                                                                <div
                                                                    wire:key="element-{{ $element['id'] }}"
                                                                    draggable="true"
                                                                    x-on:dragstart="dragItem = { kind: 'element', elementId: '{{ $element['id'] }}' }"
                                                                    @class([
                                                                        'mb-2 rounded-lg border p-3',
                                                                        'border-zinc-200 dark:border-zinc-700' => $selectedElementId !== $element['id'],
                                                                        'border-indigo-400 ring-2 ring-indigo-200 dark:border-indigo-500 dark:ring-indigo-900' => $selectedElementId === $element['id'],
                                                                    ])
                                                                    wire:click.stop="selectElement('{{ $row['id'] }}', '{{ $column['id'] }}', '{{ $element['id'] }}')"
                                                                >
                                                                    <div class="mb-2 flex items-center justify-between gap-2">
                                                                        <flux:text class="font-medium">{{ __($elementPalette[$element['type']]['label'] ?? str($element['type'])->headline()) }}</flux:text>
                                                                        <flux:button type="button" size="xs" variant="ghost" wire:click.stop="removeElement('{{ $element['id'] }}')">
                                                                            {{ __('Remove') }}
                                                                        </flux:button>
                                                                    </div>

                                                                    @if ($selectedElementId === $element['id'])
                                                                        @if ($element['type'] === 'text')
                                                                            <flux:textarea wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.content.text" :label="__('Text')" rows="4" required />
                                                                            <div class="mt-2 flex flex-wrap gap-2">
                                                                                @foreach ($availableVariables as $variable)
                                                                                    <flux:button
                                                                                        type="button"
                                                                                        size="xs"
                                                                                        variant="ghost"
                                                                                        wire:click="insertVariable('rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.content.text', '{{ $variable }}')"
                                                                                    >
                                                                                        {{ $variable }}
                                                                                    </flux:button>
                                                                                @endforeach
                                                                            </div>
                                                                        @endif

                                                                        @if ($element['type'] === 'image')
                                                                            <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.content.url" :label="__('Image URL')" type="url" required />
                                                                            <flux:input wire:model="imageUploads.{{ $rowIndex }}.{{ $columnIndex }}.{{ $elementIndex }}" :label="__('Upload Image')" type="file" accept="image/*" class="mt-3" />
                                                                            <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.content.alt" :label="__('Alt')" type="text" class="mt-3" />
                                                                        @endif

                                                                        @if ($element['type'] === 'button')
                                                                            <div class="grid gap-3 md:grid-cols-2">
                                                                                <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.content.text" :label="__('Text')" type="text" required />
                                                                                <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.content.url" :label="__('URL')" type="url" required />
                                                                            </div>
                                                                        @endif

                                                                        @if ($element['type'] === 'spacer')
                                                                            <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.content.height" :label="__('Height')" type="number" min="4" max="180" />
                                                                        @endif

                                                                        @if ($element['type'] === 'social')
                                                                            <flux:text class="text-xs">{{ __('Edit social link labels and URLs:') }}</flux:text>
                                                                            <div class="grid gap-2 md:grid-cols-2">
                                                                                <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.content.items.0.label" :label="__('Label 1')" type="text" />
                                                                                <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.content.items.0.url" :label="__('URL 1')" type="text" />
                                                                                <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.content.items.1.label" :label="__('Label 2')" type="text" />
                                                                                <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.content.items.1.url" :label="__('URL 2')" type="text" />
                                                                            </div>
                                                                        @endif

                                                                        <div class="mt-3 grid gap-2 md:grid-cols-2">
                                                                            <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.style.font_size" :label="__('Font Size')" type="number" min="10" max="56" />
                                                                            <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.style.font_weight" :label="__('Font Weight')" type="number" min="100" max="900" />
                                                                            <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.style.margin_top" :label="__('Margin Top')" type="number" min="0" max="80" />
                                                                            <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.style.margin_bottom" :label="__('Margin Bottom')" type="number" min="0" max="120" />
                                                                            <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.style.border_radius" :label="__('Radius')" type="number" min="0" max="32" />
                                                                            <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.style.width_percent" :label="__('Width %')" type="number" min="10" max="100" />
                                                                            <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.style.color" :label="__('Text Color')" type="color" />
                                                                            <flux:input wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.style.background_color" :label="__('Background')" type="color" />
                                                                        </div>
                                                                        <flux:radio.group wire:model="rows.{{ $rowIndex }}.columns.{{ $columnIndex }}.elements.{{ $elementIndex }}.style.text_align" :label="__('Alignment')" class="mt-2">
                                                                            <flux:radio value="left" :label="__('Left')" />
                                                                            <flux:radio value="center" :label="__('Center')" />
                                                                            <flux:radio value="right" :label="__('Right')" />
                                                                        </flux:radio.group>
                                                                    @endif
                                                                </div>

                                                                <div
                                                                    class="mb-2 rounded-md border border-dashed border-zinc-300 px-2 py-1 text-center text-xs text-zinc-500 dark:border-zinc-600"
                                                                    x-on:dragover.prevent
                                                                    x-on:drop.prevent="
                                                                        if (!dragItem) return;
                                                                        if (dragItem.kind === 'palette-element') { $wire.addElement('{{ $row['id'] }}', '{{ $column['id'] }}', dragItem.type, {{ $elementIndex + 1 }}); }
                                                                        if (dragItem.kind === 'element') { $wire.moveElement(dragItem.elementId, '{{ $row['id'] }}', '{{ $column['id'] }}', {{ $elementIndex + 1 }}); }
                                                                        dragItem = null;
                                                                    "
                                                                >
                                                                    {{ __('Drop after element') }}
                                                                </div>
                                                            @empty
                                                                <flux:text class="text-xs">{{ __('No elements in this column yet.') }}</flux:text>
                                                            @endforelse
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div
                                                wire:key="row-drop-after-{{ $row['id'] }}"
                                                class="rounded-md border border-dashed border-zinc-300 px-3 py-2 text-center text-xs text-zinc-500 dark:border-zinc-600"
                                                x-on:dragover.prevent
                                                x-on:drop.prevent="
                                                    if (!dragItem) return;
                                                    if (dragItem.kind === 'row-preset') { $wire.addRowPresetAt(dragItem.columns, {{ $rowIndex + 1 }}); }
                                                    if (dragItem.kind === 'row') { $wire.moveRow(dragItem.rowId, {{ $rowIndex + 1 }}); }
                                                    dragItem = null;
                                                "
                                            >
                                                {{ __('Drop row here') }}
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                        @else
                            <div class="space-y-4">
                                <flux:text class="text-sm">{{ __('Preview data: first_name=Jane, company=Acme, unsubscribe_url=https://example.com/unsubscribe') }}</flux:text>

                                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                    <flux:text class="font-medium">{{ __('Subject') }}: {{ $this->previewSubject() }}</flux:text>
                                </div>

                                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                    {!! $this->previewHtml() !!}
                                </div>
                            </div>
                        @endif

                        @if ($this->isOverAttachmentLimit)
                            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-800">
                                <div class="flex items-center text-red-700 dark:text-red-400">
                                    <flux:icon.exclamation-triangle class="w-5 h-5 mr-2" />
                                    <span class="text-sm font-medium">{{ __('Cannot save: Total attachment size exceeds 40MB limit.') }}</span>
                                </div>
                            </div>
                        @endif

                        <div class="mt-6 flex items-center justify-end gap-2">
                            <flux:button type="button" wire:click="cancelEditing" variant="ghost">
                                {{ __('Cancel') }}
                            </flux:button>
                            <flux:button 
                                variant="primary" 
                                type="submit"
                                x-bind:disabled="$wire.$get('isOverAttachmentLimit')"
                            >
                                {{ $isEditing ? __('Update Template') : __('Save Template') }}
                            </flux:button>
                        </div>
                    </div>

                    @include('livewire.templates.builder-page-step2')
                </div>
            @endif
        </form>
    </div>
</section>
