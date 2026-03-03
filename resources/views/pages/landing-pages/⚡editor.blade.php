<?php

use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Support\LandingPages\LandingPageRenderer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Vite;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $landingPageId = null;

    public ?int $selectedTemplateId = null;

    public string $title = '';

    public string $slug = '';

    public string $customDomain = '';

    public bool $slugManuallyEdited = false;

    public string $status = LandingPage::STATUS_DRAFT;

    public bool $showPreviewModal = false;

    public string $previewViewport = 'desktop';

    /**
     * @var array<string, mixed>
     */
    public array $meta = [
        'title' => '',
        'description' => '',
        'og_title' => '',
        'og_description' => '',
        'og_image' => '',
        'noindex' => false,
    ];

    /**
     * @var array<string, mixed>
     */
    public array $formData = [];

    /**
     * @var array<string, mixed>
     */
    public array $templateSnapshot = [];

    public function mount(LandingPage|int|string|null $landingPage = null): void
    {
        $editable = $this->resolveLandingPage($landingPage);

        if ($editable !== null) {
            $this->landingPageId = $editable->id;
            $this->selectedTemplateId = $editable->landing_page_template_id;
            $this->title = $editable->title;
            $this->slug = $editable->slug;
            $this->customDomain = (string) ($editable->custom_domain ?? '');
            $this->slugManuallyEdited = true;
            $this->status = $editable->status;
            $this->meta = array_replace($this->defaultMeta(), is_array($editable->meta) ? $editable->meta : []);
            $this->formData = is_array($editable->form_data) ? $editable->form_data : [];
            $this->templateSnapshot = is_array($editable->template_snapshot) ? $editable->template_snapshot : [];

            return;
        }

        $firstTemplate = $this->templates->first();

        if ($firstTemplate !== null) {
            $this->applyTemplate($firstTemplate);
        }
    }

    public function updatedTitle(string $value): void
    {
        if ($this->slugManuallyEdited) {
            return;
        }

        $this->slug = $this->generateUniqueSlug($value);
        $this->meta['title'] = $value;

        if (($this->meta['og_title'] ?? '') === '') {
            $this->meta['og_title'] = $value;
        }
    }

    public function updatedSlug(string $value): void
    {
        $this->slugManuallyEdited = true;
        $this->slug = (string) str($value)->slug('-');
    }

    public function updatedCustomDomain(string $value): void
    {
        $this->customDomain = strtolower(trim($value));
    }

    public function updatedSelectedTemplateId(string|int|null $value): void
    {
        if ($this->landingPageId !== null) {
            return;
        }

        $templateId = (int) $value;
        $template = $this->templates->firstWhere('id', $templateId);

        if (! $template instanceof LandingPageTemplate) {
            return;
        }

        $this->applyTemplate($template);
    }

    public function saveDraft(): void
    {
        $this->persist(LandingPage::STATUS_DRAFT);
    }

    public function publish(): void
    {
        $this->persist(LandingPage::STATUS_PUBLISHED);
    }

    public function openPreviewModal(): void
    {
        $this->showPreviewModal = true;
    }

    public function setPreviewViewport(string $viewport): void
    {
        if (! in_array($viewport, ['desktop', 'mobile'], true)) {
            return;
        }

        $this->previewViewport = $viewport;
    }

    #[Computed]
    public function templates(): Collection
    {
        return LandingPageTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function fieldDefinitions(): array
    {
        $schema = $this->templateSnapshot['schema'] ?? [];

        if (! is_array($schema) || ! is_array($schema['fields'] ?? null)) {
            return [];
        }

        return $schema['fields'];
    }

    #[Computed]
    public function previewHtml(): string
    {
        if ($this->templateSnapshot === []) {
            return '<div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center text-zinc-500">Select a template to preview.</div>';
        }

        try {
            return app(LandingPageRenderer::class)->render($this->templateSnapshot, $this->formData);
        } catch (\Throwable) {
            return '<div class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700">Preview unavailable due to invalid template metadata.</div>';
        }
    }

    #[Computed]
    public function previewDocument(): string
    {
        $head = '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';

        if (! $this->isStandaloneTemplate()) {
            $head .= '<link rel="stylesheet" href="'.e(Vite::asset('resources/css/app.css')).'">';
        } else {
            $head .= '<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>';
        }

        return '<!doctype html><html><head>'.$head.'</head><body style="margin:0;">'
            .$this->previewHtml
            .'</body></html>';
    }

    protected function isStandaloneTemplate(): bool
    {
        $renderMode = (string) data_get($this->templateSnapshot, 'schema.meta.render_mode', '');

        if ($renderMode === '' && $this->selectedTemplateId !== null) {
            $template = $this->templates->firstWhere('id', (int) $this->selectedTemplateId);
            $renderMode = $template instanceof LandingPageTemplate
                ? (string) data_get($template->schema, 'meta.render_mode', 'app')
                : 'app';
        }

        return $renderMode === 'standalone';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'selectedTemplateId' => ['required', 'integer', 'exists:landing_page_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('landing_pages', 'slug')->ignore($this->landingPageId),
            ],
            'customDomain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?=.{1,255}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',
                Rule::unique('landing_pages', 'custom_domain')->ignore($this->landingPageId),
            ],
            'meta.title' => ['required', 'string', 'max:255'],
            'meta.description' => ['nullable', 'string', 'max:320'],
            'meta.og_title' => ['nullable', 'string', 'max:255'],
            'meta.og_description' => ['nullable', 'string', 'max:320'],
            'meta.og_image' => ['nullable', 'url', 'max:2048'],
            'meta.noindex' => ['required', 'boolean'],
        ];

        foreach ($this->fieldDefinitions as $field) {
            $fieldKey = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'text');

            if ($fieldKey === '') {
                continue;
            }

            $path = 'formData.'.$fieldKey;
            $fieldRules = [];

            $fieldRules[] = (bool) ($field['required'] ?? false) ? 'required' : 'nullable';

            if (in_array($type, ['text', 'textarea', 'richtext'], true)) {
                $fieldRules[] = 'string';

                if (isset($field['max']) && is_numeric($field['max'])) {
                    $fieldRules[] = 'max:'.(int) $field['max'];
                }
            }

            if ($type === 'color') {
                $fieldRules[] = 'regex:/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/';
            }

            if (in_array($type, ['image_url', 'url'], true)) {
                $fieldRules[] = 'url';
                $fieldRules[] = 'max:2048';
            }

            if ($type === 'number') {
                $fieldRules[] = 'numeric';

                if (isset($field['min']) && is_numeric($field['min'])) {
                    $fieldRules[] = 'min:'.$field['min'];
                }

                if (isset($field['max']) && is_numeric($field['max'])) {
                    $fieldRules[] = 'max:'.$field['max'];
                }
            }

            if ($type === 'select') {
                $allowedValues = collect($field['options'] ?? [])
                    ->map(fn (mixed $option): string => is_array($option) ? (string) ($option['value'] ?? '') : (string) $option)
                    ->filter()
                    ->values()
                    ->all();

                $fieldRules[] = Rule::in($allowedValues);
            }

            if ($type === 'toggle') {
                $fieldRules[] = 'boolean';
            }

            $rules[$path] = $fieldRules;
        }

        return $rules;
    }

    protected function persist(string $status): void
    {
        if ($this->templateSnapshot === []) {
            $this->addError('selectedTemplateId', __('Please select a template.'));

            return;
        }

        $this->status = $status;

        $validated = $this->validate();
        $this->validateCustomDomainAllowed($validated['customDomain'] ?? null);
        $this->guardUnknownFields();

        $attributes = [
            'landing_page_template_id' => (int) $validated['selectedTemplateId'],
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'custom_domain' => $validated['customDomain'] ?: null,
            'status' => $status,
            'meta' => $validated['meta'],
            'form_data' => $this->formData,
            'template_snapshot' => $this->templateSnapshot,
            'published_at' => $status === LandingPage::STATUS_PUBLISHED ? now() : null,
        ];

        if ($this->landingPageId === null) {
            $landingPage = LandingPage::query()->create(array_merge($attributes, [
                'user_id' => (int) auth()->id(),
            ]));

            $this->landingPageId = $landingPage->id;
        } else {
            $landingPage = LandingPage::query()
                ->where('id', $this->landingPageId)
                ->firstOrFail();

            if ($status === LandingPage::STATUS_PUBLISHED && $landingPage->published_at !== null) {
                $attributes['published_at'] = $landingPage->published_at;
            }

            $landingPage->update($attributes);
        }

        $this->redirect(route('landing-pages.index'), navigate: true);
    }

    protected function guardUnknownFields(): void
    {
        $allowed = collect($this->fieldDefinitions)
            ->map(fn (array $field): string => (string) ($field['key'] ?? ''))
            ->filter()
            ->values()
            ->all();

        $unexpected = array_diff(array_keys($this->formData), $allowed);

        if ($unexpected === []) {
            return;
        }

        throw ValidationException::withMessages([
            'formData' => __('Unknown field keys submitted: :keys', ['keys' => implode(', ', $unexpected)]),
        ]);
    }

    protected function applyTemplate(LandingPageTemplate $template): void
    {
        $this->selectedTemplateId = $template->id;
        $this->templateSnapshot = [
            'key' => $template->key,
            'name' => $template->name,
            'description' => $template->description,
            'view_path' => $template->view_path,
            'version' => $template->version,
            'schema' => is_array($template->schema) ? $template->schema : ['fields' => []],
        ];

        $this->formData = $this->normalizeFormDataFromSchema($this->templateSnapshot['schema'], $this->formData);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    protected function normalizeFormDataFromSchema(array $schema, array $current): array
    {
        $normalized = [];
        $fields = $schema['fields'] ?? [];

        if (! is_array($fields)) {
            return $normalized;
        }

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'text');

            if ($key === '') {
                continue;
            }

            if (array_key_exists($key, $current)) {
                $normalized[$key] = $current[$key];

                continue;
            }

            if (array_key_exists('default', $field)) {
                $normalized[$key] = $field['default'];

                continue;
            }

            $normalized[$key] = match ($type) {
                'number' => 0,
                'toggle' => false,
                'color' => '#111827',
                default => '',
            };
        }

        return $normalized;
    }

    protected function resolveLandingPage(LandingPage|int|string|null $landingPage): ?LandingPage
    {
        if ($landingPage instanceof LandingPage) {
            return LandingPage::query()
                ->where('id', $landingPage->id)
                ->firstOrFail();
        }

        if (is_numeric($landingPage)) {
            return LandingPage::query()
                ->where('id', (int) $landingPage)
                ->firstOrFail();
        }

        return null;
    }

    protected function generateUniqueSlug(string $value): string
    {
        $base = (string) str($value)->slug('-');

        if ($base === '') {
            $base = 'event-page';
        }

        $slug = $base;
        $suffix = 2;

        while (
            LandingPage::query()
                ->where('slug', $slug)
                ->when($this->landingPageId !== null, fn ($query) => $query->where('id', '!=', $this->landingPageId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function validateCustomDomainAllowed(mixed $domain): void
    {
        if (! is_string($domain) || trim($domain) === '') {
            return;
        }

        $domain = strtolower(trim($domain));

        if ($this->isDomainAllowed($domain)) {
            return;
        }

        throw ValidationException::withMessages([
            'customDomain' => __('Domain is not allowed. Update LANDING_PAGE_DOMAINS or LANDING_PAGE_WILDCARD_ROOT.'),
        ]);
    }

    protected function isDomainAllowed(string $domain): bool
    {
        $exactDomains = collect(config('landing-pages.domains', []))
            ->filter(fn (mixed $item): bool => is_string($item) && $item !== '')
            ->map(fn (string $item): string => strtolower($item))
            ->values()
            ->all();

        if (in_array($domain, $exactDomains, true)) {
            return true;
        }

        $wildcardRoot = strtolower((string) config('landing-pages.wildcard_root', ''));

        if ($wildcardRoot === '') {
            return false;
        }

        return str_ends_with($domain, '.'.$wildcardRoot);
    }

    /**
     * @return array<string, string|bool>
     */
    protected function defaultMeta(): array
    {
        return [
            'title' => '',
            'description' => '',
            'og_title' => '',
            'og_description' => '',
            'og_image' => '',
            'noindex' => false,
        ];
    }
}; ?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6 lg:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <flux:heading size="xl">{{ $landingPageId ? __('Edit Landing Page') : __('Create Landing Page') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Choose a design, fill schema-driven fields, and publish to a reusable event URL.') }}</flux:text>
            </div>

            <div class="flex items-center gap-2">
                <flux:button type="button" variant="ghost" wire:click="openPreviewModal">
                    {{ __('Open Preview') }}
                </flux:button>

                <flux:button :href="route('landing-pages.index')" variant="ghost" wire:navigate>
                    {{ __('Back to Landing Pages') }}
                </flux:button>
            </div>
        </div>

        <div class="space-y-5 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:field>
                    <flux:label>{{ __('Template') }}</flux:label>
                    <flux:select wire:model.live="selectedTemplateId" :disabled="$landingPageId !== null" required>
                        <flux:select.option value="">{{ __('Choose template') }}</flux:select.option>
                        @foreach ($this->templates as $template)
                            <flux:select.option wire:key="landing-template-option-{{ $template->id }}" value="{{ $template->id }}">
                                {{ $template->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="selectedTemplateId" />
                </flux:field>

                <flux:input wire:model.live="title" :label="__('Page Title')" type="text" required />
                <flux:error name="title" />

                <flux:input wire:model.blur="slug" :label="__('Slug (URL)')" type="text" required />
                <flux:error name="slug" />

                <flux:input wire:model.blur="customDomain" :label="__('Custom Domain (optional)')" type="text" placeholder="event.example.com" />
                <flux:error name="customDomain" />
                <flux:text class="text-xs text-zinc-500">
                    {{ __('Allowed via LANDING_PAGE_DOMAINS or LANDING_PAGE_WILDCARD_ROOT.') }}
                </flux:text>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Metadata') }}</flux:heading>
                    <div class="mt-3 space-y-3">
                        <flux:input wire:model="meta.title" :label="__('Meta Title')" type="text" required />
                        <flux:textarea wire:model="meta.description" :label="__('Meta Description')" rows="3" />
                        <flux:input wire:model="meta.og_title" :label="__('OG Title')" type="text" />
                        <flux:textarea wire:model="meta.og_description" :label="__('OG Description')" rows="3" />
                        <flux:input wire:model="meta.og_image" :label="__('OG Image URL')" type="url" />

                        <flux:field>
                            <flux:label>{{ __('Noindex') }}</flux:label>
                            <flux:switch wire:model="meta.noindex" />
                        </flux:field>
                    </div>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Template Fields') }}</flux:heading>

                    @if ($this->fieldDefinitions === [])
                        <flux:text class="mt-2 text-sm">{{ __('Select a template to configure fields.') }}</flux:text>
                    @else
                        <div class="mt-3 space-y-3">
                            @foreach ($this->fieldDefinitions as $fieldIndex => $field)
                                @php
                                    $fieldKey = (string) ($field['key'] ?? '');
                                    $fieldType = (string) ($field['type'] ?? 'text');
                                    $fieldLabel = (string) ($field['label'] ?? $fieldKey);
                                    $fieldRequired = (bool) ($field['required'] ?? false);
                                @endphp

                                <div wire:key="landing-page-template-{{ $selectedTemplateId ?? 'none' }}-field-{{ $fieldKey }}-{{ $fieldIndex }}">
                                    @if ($fieldType === 'textarea' || $fieldType === 'richtext')
                                        <flux:textarea
                                            wire:model="formData.{{ $fieldKey }}"
                                            :label="$fieldLabel"
                                            rows="{{ $fieldType === 'richtext' ? 6 : 4 }}"
                                            :required="$fieldRequired"
                                        />
                                    @elseif ($fieldType === 'color')
                                        <flux:input wire:model="formData.{{ $fieldKey }}" :label="$fieldLabel" type="color" :required="$fieldRequired" />
                                    @elseif ($fieldType === 'image_url' || $fieldType === 'url')
                                        <flux:input wire:model="formData.{{ $fieldKey }}" :label="$fieldLabel" type="url" :required="$fieldRequired" />
                                    @elseif ($fieldType === 'number')
                                        <flux:input
                                            wire:model="formData.{{ $fieldKey }}"
                                            :label="$fieldLabel"
                                            type="number"
                                            :min="isset($field['min']) ? (string) $field['min'] : null"
                                            :max="isset($field['max']) ? (string) $field['max'] : null"
                                            :required="$fieldRequired"
                                        />
                                    @elseif ($fieldType === 'select')
                                        <flux:field>
                                            <flux:label>{{ $fieldLabel }}</flux:label>
                                            <flux:select wire:model="formData.{{ $fieldKey }}" :required="$fieldRequired">
                                                <flux:select.option value="">{{ __('Choose option') }}</flux:select.option>
                                                @foreach ((array) ($field['options'] ?? []) as $option)
                                                    <flux:select.option value="{{ is_array($option) ? ($option['value'] ?? '') : $option }}">
                                                        {{ is_array($option) ? ($option['label'] ?? $option['value'] ?? '') : $option }}
                                                    </flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </flux:field>
                                    @elseif ($fieldType === 'toggle')
                                        <flux:field>
                                            <flux:label>{{ $fieldLabel }}</flux:label>
                                            <flux:switch wire:model="formData.{{ $fieldKey }}" />
                                        </flux:field>
                                    @else
                                        <flux:input wire:model="formData.{{ $fieldKey }}" :label="$fieldLabel" type="text" :required="$fieldRequired" />
                                    @endif

                                    <flux:error name="formData.{{ $fieldKey }}" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="openPreviewModal">
                        {{ __('Open Preview') }}
                    </flux:button>
                    <flux:button type="button" variant="ghost" wire:click="saveDraft">
                        {{ __('Save Draft') }}
                    </flux:button>
                    <flux:button type="button" variant="primary" wire:click="publish">
                        {{ __('Publish') }}
                    </flux:button>
                </div>
        </div>
    </div>

    <flux:modal wire:model="showPreviewModal" class="max-w-[98vw]">
        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ __('Landing Page Preview') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('Rendering from template snapshot + current form data at fixed viewport width.') }}</flux:text>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button
                        type="button"
                        size="sm"
                        :variant="$previewViewport === 'desktop' ? 'primary' : 'ghost'"
                        wire:click="setPreviewViewport('desktop')"
                    >
                        {{ __('Desktop') }}
                    </flux:button>
                    <flux:button
                        type="button"
                        size="sm"
                        :variant="$previewViewport === 'mobile' ? 'primary' : 'ghost'"
                        wire:click="setPreviewViewport('mobile')"
                    >
                        {{ __('Mobile') }}
                    </flux:button>
                </div>
            </div>

            <div class="overflow-auto rounded-xl border border-zinc-200 bg-zinc-100 p-4 dark:border-zinc-700 dark:bg-zinc-950">
                @php
                    $previewCanvasClass = $previewViewport === 'mobile'
                        ? 'mx-auto h-[844px] w-[390px] max-w-[390px] overflow-hidden rounded-lg bg-white shadow-sm'
                        : 'h-[860px] w-[1440px] min-w-[1440px] overflow-hidden rounded-lg bg-white shadow-sm';
                @endphp
                <iframe
                    title="{{ __('Landing Page Preview') }}"
                    class="{{ $previewCanvasClass }}"
                    srcdoc="{{ $this->previewDocument }}"
                ></iframe>
            </div>

            <div class="flex items-center justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showPreviewModal', false)">
                    {{ __('Close') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
