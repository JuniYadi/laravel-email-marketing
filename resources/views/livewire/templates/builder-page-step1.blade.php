<div class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('Template Setup') }}</flux:heading>
        <flux:text class="mt-1 text-sm">{{ __('Set name, subject, and theme before building the canvas.') }}</flux:text>
    </div>

    <flux:input wire:model="name" :label="__('Template Name')" type="text" required />

    <div>
        <flux:input wire:model="subject" :label="__('Subject Line')" type="text" required />
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach ($availableVariables as $variable)
                <flux:button type="button" size="xs" variant="ghost" wire:click="insertVariable('subject', '{{ $variable }}')">
                    {{ $variable }}
                </flux:button>
            @endforeach
        </div>
    </div>

    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="sm">{{ __('Theme') }}</flux:heading>
            <flux:button 
                type="button" 
                size="xs" 
                variant="ghost"
                wire:click="toggleThemeSettings"
            >
                @if($showThemeSettings) 
                    {{ __('Hide') }}
                @else 
                    {{ __('Show') }}
                @endif
            </flux:button>
        </div>
        
        @if($showThemeSettings)
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
        @endif
    </div>

    {{-- Attachments Section --}}
    <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
        <flux:heading size="sm" class="mb-4">{{ __('Attachments') }}</flux:heading>
        <livewire:templates.attachment-upload :template-id="$templateId" />
    </div>

    <div class="flex items-center justify-between gap-3 pt-4">
        <flux:button type="button" wire:click="cancelEditing" variant="ghost">
            {{ __('Cancel') }}
        </flux:button>

        <flux:button type="button" variant="primary" wire:click="continueToBuilder">
            {{ __('Continue to Builder') }}
        </flux:button>
    </div>
</div>
