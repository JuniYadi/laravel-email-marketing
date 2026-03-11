<?php

use App\Models\LandingPage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
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

    #[Computed]
    public function landingPages(): Collection
    {
        return LandingPage::query()
            ->with('user:id,name,email')
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
                            <th class="py-2 pe-3">{{ __('Slug') }}</th>
                            <th class="py-2 pe-3">{{ __('Status') }}</th>
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
                                    @if (filled($landingPage->custom_domain))
                                        <a href="https://{{ $landingPage->custom_domain }}" class="text-sky-600 underline" target="_blank" rel="noreferrer">
                                            {{ $landingPage->custom_domain }}
                                        </a>
                                    @else
                                        <a href="{{ route('events.show', $landingPage->slug) }}" class="text-sky-600 underline" target="_blank" rel="noreferrer">
                                            /events/{{ $landingPage->slug }}
                                        </a>
                                    @endif
                                </td>
                                <td class="py-2 pe-3">
                                    @if ($landingPage->status === \App\Models\LandingPage::STATUS_PUBLISHED)
                                        <flux:badge color="emerald" size="sm">{{ __('Published') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm">{{ __('Draft') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 pe-3">{{ $landingPage->updated_at?->diffForHumans() }}</td>
                                <td class="py-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:button :href="route('landing-pages.edit', $landingPage)" size="sm" variant="ghost" wire:navigate>
                                            {{ __('Edit') }}
                                        </flux:button>

                                        @if ($landingPage->status === \App\Models\LandingPage::STATUS_DRAFT)
                                            <flux:button wire:click="publishPage({{ $landingPage->id }})" size="sm" variant="filled">
                                                {{ __('Publish') }}
                                            </flux:button>
                                        @else
                                            <flux:button wire:click="unpublishPage({{ $landingPage->id }})" size="sm" variant="ghost">
                                                {{ __('Unpublish') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-4" colspan="7">
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
