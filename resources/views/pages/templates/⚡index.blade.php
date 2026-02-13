<?php

use App\Mail\TemplateTestMail;
use App\Models\EmailTemplate;
use App\Support\TemplateRenderer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component {
    public bool $showPreviewModal = false;

    public ?int $previewTemplateId = null;
    public string $previewHtml = '';
    public string $previewSubject = '';
    public string $testEmail = '';

    /**
     * Open preview modal for selected template.
     */
    public function openPreviewModal(int $templateId): void
    {
        $template = EmailTemplate::query()->findOrFail($templateId);

        $this->previewTemplateId = $template->id;
        $this->previewSubject = $this->renderContent($template->subject);
        $this->previewHtml = $this->renderContent($template->html_content);
        $this->testEmail = auth()->user()?->email ?? '';
        $this->showPreviewModal = true;
    }

    /**
     * Send test email using selected template.
     */
    public function sendTestEmail(int $templateId): void
    {
        $validated = $this->validate([
            'testEmail' => ['required', 'email'],
        ]);

        $template = EmailTemplate::query()->findOrFail($templateId);

        Mail::to($validated['testEmail'])->send(new TemplateTestMail(
            subjectLine: $this->renderContent($template->subject),
            htmlContent: $this->renderContent($template->html_content),
        ));

        $this->dispatch('template-test-sent');
    }

    /**
     * Render placeholders using sample preview variables.
     */
    protected function renderContent(string $content): string
    {
        return app(TemplateRenderer::class)->render($content, [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'company' => 'Acme',
            'unsubscribe_url' => 'https://example.com/unsubscribe',
        ]);
    }

    #[Computed]
    public function templates(): Collection
    {
        return EmailTemplate::query()->latest()->get();
    }

    /**
     * Export templates metadata to CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $filename = sprintf('email-templates-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'id',
                'name',
                'subject',
                'version',
                'type',
                'is_active',
                'created_at',
                'updated_at',
            ], ',', '"', '');

            EmailTemplate::query()
                ->orderBy('id')
                ->chunkById(500, function (Collection $templates) use ($handle): void {
                    foreach ($templates as $template) {
                        fputcsv($handle, [
                            $template->id,
                            $template->name,
                            $template->subject,
                            $template->version,
                            is_array($template->builder_schema) ? 'visual' : 'raw',
                            $template->is_active ? '1' : '0',
                            $template->created_at?->format('Y-m-d H:i:s'),
                            $template->updated_at?->format('Y-m-d H:i:s'),
                        ], ',', '"', '');
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}; ?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Email Templates') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Design and preview real HTML templates with dynamic placeholders.') }}</flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button wire:click="exportCsv" variant="outline" icon="arrow-down-tray">
                    {{ __('Export CSV') }}
                </flux:button>
                <flux:button :href="route('templates.create')" variant="primary" wire:navigate>
                    {{ __('New Template') }}
                </flux:button>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading>{{ __('Templates') }}</flux:heading>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="py-2 pe-3">{{ __('Name') }}</th>
                            <th class="py-2 pe-3">{{ __('Subject') }}</th>
                            <th class="py-2 pe-3">{{ __('Version') }}</th>
                            <th class="py-2 pe-3">{{ __('Type') }}</th>
                            <th class="py-2 pe-3">{{ __('Status') }}</th>
                            <th class="py-2">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->templates as $template)
                            <tr wire:key="template-row-{{ $template->id }}" class="border-b border-zinc-100 align-top dark:border-zinc-800">
                                <td class="py-2 pe-3">{{ $template->name }}</td>
                                <td class="py-2 pe-3">{{ $template->subject }}</td>
                                <td class="py-2 pe-3">{{ $template->version }}</td>
                                <td class="py-2 pe-3">
                                    @if (is_array($template->builder_schema))
                                        <flux:badge color="sky" size="sm">{{ __('Visual') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm">{{ __('Raw') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 pe-3">
                                    @if ($template->is_active)
                                        <flux:badge color="emerald" size="sm">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button :href="route('templates.edit', $template)" size="sm" variant="ghost" wire:navigate>{{ __('Edit') }}</flux:button>
                                        <flux:button wire:click="openPreviewModal({{ $template->id }})" size="sm" variant="filled">{{ __('Preview') }}</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-4" colspan="6">
                                    <flux:text>{{ __('No templates yet.') }}</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <flux:modal wire:model="showPreviewModal" class="max-w-4xl">
        <div class="space-y-4">
            <flux:heading>{{ __('Template Preview') }}</flux:heading>
            <flux:text>{{ __('Preview data: first_name=Jane, last_name=Doe, company=Acme') }}</flux:text>

            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="font-medium">{{ __('Subject') }}: {{ $previewSubject }}</flux:text>
            </div>

            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                {!! $previewHtml !!}
            </div>

            @if ($previewTemplateId)
                <form wire:submit="sendTestEmail({{ $previewTemplateId }})" class="space-y-3">
                    <flux:input wire:model="testEmail" :label="__('Send Test To')" type="email" required />

                    <div class="flex items-center justify-end gap-2">
                        <flux:button wire:click="$set('showPreviewModal', false)" variant="ghost" type="button">
                            {{ __('Close') }}
                        </flux:button>
                        <flux:button variant="primary" type="submit">
                            {{ __('Send Test Email') }}
                        </flux:button>
                    </div>
                </form>
            @endif
        </div>
    </flux:modal>
</section>
