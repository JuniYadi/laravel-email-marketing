<?php

use App\Models\LandingPage;
use App\Support\LandingPages\LandingPageTemplateRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public function mount(LandingPageTemplateRegistry $registry): void
    {
        try {
            $registry->syncIfChanged(false);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function publishPage(int $landingPageId): void
    {
        $landingPage = LandingPage::query()
            ->findOrFail($landingPageId);

        $landingPage->update([
            'status' => LandingPage::STATUS_PUBLISHED,
            'published_at' => $landingPage->published_at ?? now(),
        ]);
    }

    public function unpublishPage(int $landingPageId): void
    {
        $landingPage = LandingPage::query()
            ->findOrFail($landingPageId);

        $landingPage->update([
            'status' => LandingPage::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function syncTemplates(): void
    {
        try {
            Artisan::call('landing-pages:sync-templates', [
                '--no-interaction' => true,
            ]);
        } catch (\Throwable $exception) {
            session()->flash('landing_pages_notice', __('Template sync failed: :message', [
                'message' => $exception->getMessage(),
            ]));
            session()->flash('landing_pages_notice_type', 'error');

            return;
        }

        session()->flash('landing_pages_notice', trim(Artisan::output()) ?: __('Templates synced successfully.'));
        session()->flash('landing_pages_notice_type', 'success');
    }

    public function publicPageUrl(LandingPage $landingPage): string
    {
        if (filled($landingPage->custom_domain)) {
            return 'https://'.$landingPage->custom_domain;
        }

        return route('events.show', $landingPage->slug);
    }

    #[Computed]
    public function landingPages(): Collection
    {
        return LandingPage::query()
            ->with('user:id,name,email')
            ->withCount([
                'viewEvents as real_views_count' => function ($query): void {
                    $query->where('is_bot', false);
                },
                'viewEvents as bot_views_count' => function ($query): void {
                    $query->where('is_bot', true);
                },
            ])
            ->latest('updated_at')
            ->get();
    }
}; ?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Landing Pages') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Create reusable landing pages from predefined template designs.') }}</flux:text>
            </div>

            <div class="flex items-center gap-2">
                <flux:button wire:click="syncTemplates" variant="ghost" wire:loading.attr="disabled" wire:target="syncTemplates">
                    <span wire:loading.remove wire:target="syncTemplates">{{ __('Sync Templates') }}</span>
                    <span wire:loading wire:target="syncTemplates">{{ __('Syncing...') }}</span>
                </flux:button>

                <flux:button :href="route('landing-pages.create')" variant="primary" wire:navigate>
                    {{ __('New Landing Page') }}
                </flux:button>
            </div>
        </div>

        @if (session()->has('landing_pages_notice'))
            <div @class([
                'rounded-lg border px-4 py-3 text-sm',
                'border-emerald-300 bg-emerald-50 text-emerald-800' => session('landing_pages_notice_type') !== 'error',
                'border-red-300 bg-red-50 text-red-700' => session('landing_pages_notice_type') === 'error',
            ])>
                {{ session('landing_pages_notice') }}
            </div>
        @endif

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="py-2 pe-3">{{ __('Title') }}</th>
                            <th class="py-2 pe-3">{{ __('Owner') }}</th>
                            <th class="py-2 pe-3">{{ __('Template') }}</th>
                            <th class="py-2 pe-3">{{ __('Status') }}</th>
                            <th class="py-2 pe-3">{{ __('Views') }}</th>
                            <th class="py-2 pe-3">{{ __('Bot Views') }}</th>
                            <th class="py-2 pe-3">{{ __('Updated') }}</th>
                            <th class="py-2">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->landingPages as $landingPage)
                            <tr class="border-b border-zinc-100 align-top dark:border-zinc-800" wire:key="landing-page-{{ $landingPage->id }}">
                                <td class="py-2 pe-3">{{ $landingPage->title }}</td>
                                <td class="py-2 pe-3">
                                    {{ $landingPage->user?->name ?: $landingPage->user?->email ?: __('Unknown') }}
                                </td>
                                <td class="py-2 pe-3">{{ $landingPage->template_snapshot['name'] ?? 'Template' }}</td>
                                <td class="py-2 pe-3">
                                    @if ($landingPage->status === \App\Models\LandingPage::STATUS_PUBLISHED)
                                        <flux:badge color="emerald" size="sm">{{ __('Published') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm">{{ __('Draft') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 pe-3">{{ (int) $landingPage->real_views_count }}</td>
                                <td class="py-2 pe-3">{{ (int) $landingPage->bot_views_count }}</td>
                                <td class="py-2 pe-3">{{ $landingPage->updated_at?->diffForHumans() }}</td>
                                <td class="py-2">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />

                                        <flux:menu>
                                            <flux:menu.item :href="$this->publicPageUrl($landingPage)" icon="arrow-top-right-on-square" target="_blank" rel="noreferrer">
                                                {{ __('Open Page') }}
                                            </flux:menu.item>

                                            <flux:menu.item :href="route('landing-pages.edit', $landingPage)" icon="pencil" wire:navigate>
                                                {{ __('Edit') }}
                                            </flux:menu.item>

                                            <flux:menu.item :href="route('landing-pages.history', ['landing_page_id' => $landingPage->id])" icon="clock" wire:navigate>
                                                {{ __('History') }}
                                            </flux:menu.item>

                                            <flux:menu.separator />

                                            @if ($landingPage->status === \App\Models\LandingPage::STATUS_DRAFT)
                                                <flux:menu.item wire:click="publishPage({{ $landingPage->id }})" icon="bolt">
                                                    {{ __('Publish') }}
                                                </flux:menu.item>
                                            @else
                                                <flux:menu.item wire:click="unpublishPage({{ $landingPage->id }})" icon="x-mark">
                                                    {{ __('Unpublish') }}
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-4" colspan="8">
                                    <flux:text>{{ __('No landing pages yet.') }}</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
