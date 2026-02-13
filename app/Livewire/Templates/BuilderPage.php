<?php

namespace App\Livewire\Templates;

use App\Models\EmailTemplate;
use App\Support\EmailTemplateBuilderRenderer;
use App\Support\TemplateBuilder\CanvasStateManager;
use App\Support\TemplateBuilder\HistoryManager;
use App\Support\TemplateBuilder\SchemaMigrator;
use App\Support\TemplateRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class BuilderPage extends Component
{
    use WithFileUploads;

    public ?int $templateId = null;

    public ?EmailTemplate $template = null;

    public bool $isEditing = false;

    public bool $isLegacyTemplate = false;

    public int $schemaVersion = 2;

    public int $currentStep = 1;

    public string $workspaceTab = 'builder';

    public string $sidebarTab = 'rows';

    public string $mode = 'visual';

    public string $templateKey = 'welcome';

    public string $name = '';

    public string $subject = 'Welcome {{ first_name }}';

    public string $htmlContent = "<h1>Hello {{ first_name }}</h1>\n<p>Welcome to {{ company }}</p>";

    public bool $isActive = true;

    /**
     * @var array<string, string|int>
     */
    public array $theme = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    /**
     * @var array<int, array<int, array<int, UploadedFile|null>>>
     */
    public array $imageUploads = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $historySnapshots = [];

    public int $historyCursor = -1;

    public bool $skipHistoryCapture = false;

    public ?string $selectedRowId = null;

    public ?string $selectedColumnId = null;

    public ?string $selectedElementId = null;

    /**
     * @var array<int, array{label: string}>
     */
    public array $rowPresets = [
        1 => ['label' => '1 Column'],
        2 => ['label' => '2 Columns'],
    ];

    /**
     * @var array<string, array{label: string, description: string}>
     */
    public array $elementPalette = [
        'text' => ['label' => 'Text', 'description' => 'Paragraphs, headlines, and copy.'],
        'image' => ['label' => 'Image', 'description' => 'Single image with full style control.'],
        'button' => ['label' => 'Button', 'description' => 'Optional call-to-action button.'],
        'divider' => ['label' => 'Divider', 'description' => 'Horizontal separator line.'],
        'spacer' => ['label' => 'Spacer', 'description' => 'Adjustable vertical spacing.'],
        'social' => ['label' => 'Social', 'description' => 'List of social/profile links.'],
    ];

    /**
     * @var list<string>
     */
    public array $availableVariables = [
        'first_name',
        'last_name',
        'full_name',
        'email',
        'company',
        'unsubscribe_url',
    ];

    /**
     * @var array<string, string>
     */
    public array $starterTemplateOptions = [];

    public function mount(EmailTemplate|int|string|null $template = null): void
    {
        $this->theme = $this->defaultTheme();
        $this->starterTemplateOptions = collect($this->starterTemplates())
            ->map(fn (array $item): string => $item['label'])
            ->all();

        $step = (int) request('step', 1);
        $resolvedTemplate = $this->resolveTemplate($template);

        if ($resolvedTemplate === null) {
            $this->applyStarterTemplate($this->templateKey);
            $this->captureHistory();
            $this->currentStep = max(1, min(2, $step));

            return;
        }

        $this->isEditing = true;
        $this->templateId = $resolvedTemplate->id;
        $this->template = $resolvedTemplate;
        $this->name = $resolvedTemplate->name;
        $this->subject = $resolvedTemplate->subject;
        $this->htmlContent = $resolvedTemplate->html_content;
        $this->isActive = $resolvedTemplate->is_active;
        $this->currentStep = max(2, min(2, $step));

        if (! is_array($resolvedTemplate->builder_schema)) {
            $this->mode = 'raw';
            $this->isLegacyTemplate = true;
            $this->templateKey = 'blank';
            $this->rows = [];

            return;
        }

        $migratedSchema = app(SchemaMigrator::class)->migrate($resolvedTemplate->builder_schema);

        if ($migratedSchema === null) {
            $this->mode = 'raw';
            $this->isLegacyTemplate = true;
            $this->templateKey = 'blank';
            $this->rows = [];

            return;
        }

        $this->mode = 'visual';
        $this->schemaVersion = 2;
        $this->templateKey = (string) ($migratedSchema['meta']['template_key'] ?? 'blank');
        $this->theme = array_replace($this->defaultTheme(), (array) ($migratedSchema['theme'] ?? []));
        $this->rows = $this->normalizeRows((array) ($migratedSchema['rows'] ?? []));
        $this->hydrateSelection();
        $this->captureHistory();
    }

    public function updated(string $property): void
    {
        if ($this->skipHistoryCapture || $this->mode !== 'visual') {
            return;
        }

        if (
            $property === 'rows' ||
            $property === 'theme' ||
            str_starts_with($property, 'rows.') ||
            str_starts_with($property, 'theme.')
        ) {
            $this->captureHistory();
        }
    }

    public function updatedMode(string $mode): void
    {
        if ($mode === 'visual' && $this->rows === []) {
            $this->applyStarterTemplate($this->templateKey === 'blank' ? 'welcome' : $this->templateKey);
            $this->isLegacyTemplate = false;
            $this->captureHistory();
        }
    }

    public function updatedTemplateKey(string $templateKey): void
    {
        if ($this->mode === 'visual') {
            $this->applyStarterTemplate($templateKey);
            $this->captureHistory();
        }
    }

    public function updatedImageUploads(mixed $value, string $key): void
    {
        [$rowIndex, $columnIndex, $elementIndex] = array_map('intval', explode('.', $key));

        if (
            ! isset($this->rows[$rowIndex]['columns'][$columnIndex]['elements'][$elementIndex]) ||
            ! isset($this->imageUploads[$rowIndex][$columnIndex][$elementIndex])
        ) {
            return;
        }

        $this->validate([
            "imageUploads.$rowIndex.$columnIndex.$elementIndex" => ['nullable', 'image', 'max:4096'],
        ]);

        $file = $this->imageUploads[$rowIndex][$columnIndex][$elementIndex] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $path = $file->store(path: 'email-templates', options: 'public');
        $url = Storage::disk('public')->url($path);

        $element = $this->rows[$rowIndex]['columns'][$columnIndex]['elements'][$elementIndex];

        if (($element['type'] ?? '') === 'image') {
            $this->rows[$rowIndex]['columns'][$columnIndex]['elements'][$elementIndex]['content']['url'] = $url;
        }

        unset($this->imageUploads[$rowIndex][$columnIndex][$elementIndex]);

        $this->captureHistory();
    }

    public function addRowPreset(int $columns): void
    {
        $this->rows = app(CanvasStateManager::class)->addRowPreset($this->rows, $columns);
        $this->hydrateSelection();
        $this->captureHistory();
    }

    public function addRowPresetAt(int $columns, int $position): void
    {
        $this->rows = app(CanvasStateManager::class)->addRowPreset($this->rows, $columns, $position);
        $this->hydrateSelection();
        $this->captureHistory();
    }

    public function moveRow(string $rowId, int $position): void
    {
        $this->rows = app(CanvasStateManager::class)->moveRow($this->rows, $rowId, $position);
        $this->captureHistory();
    }

    public function removeRow(string $rowId): void
    {
        $this->rows = app(CanvasStateManager::class)->removeRow($this->rows, $rowId);
        $this->hydrateSelection();
        $this->captureHistory();
    }

    public function addElement(string $rowId, string $columnId, string $type, int $position = 999): void
    {
        if (! array_key_exists($type, $this->elementPalette)) {
            return;
        }

        $element = $this->newElement($type);
        $this->rows = app(CanvasStateManager::class)->insertElement($this->rows, $rowId, $columnId, $element, $position);
        $this->selectedElementId = $element['id'];
        $this->selectedColumnId = $columnId;
        $this->selectedRowId = $rowId;
        $this->captureHistory();
    }

    public function removeElement(string $elementId): void
    {
        $this->rows = app(CanvasStateManager::class)->removeElement($this->rows, $elementId);

        if ($this->selectedElementId === $elementId) {
            $this->selectedElementId = null;
        }

        $this->captureHistory();
    }

    public function moveElement(string $elementId, string $targetRowId, string $targetColumnId, int $targetPosition): void
    {
        $this->rows = app(CanvasStateManager::class)->moveElement(
            $this->rows,
            $elementId,
            $targetRowId,
            $targetColumnId,
            $targetPosition,
        );
        $this->selectedElementId = $elementId;
        $this->selectedColumnId = $targetColumnId;
        $this->selectedRowId = $targetRowId;
        $this->captureHistory();
    }

    public function selectRow(string $rowId): void
    {
        $this->selectedRowId = $rowId;
        $this->selectedColumnId = null;
        $this->selectedElementId = null;
    }

    public function selectColumn(string $rowId, string $columnId): void
    {
        $this->selectedRowId = $rowId;
        $this->selectedColumnId = $columnId;
        $this->selectedElementId = null;
    }

    public function selectElement(string $rowId, string $columnId, string $elementId): void
    {
        $this->selectedRowId = $rowId;
        $this->selectedColumnId = $columnId;
        $this->selectedElementId = $elementId;
    }

    public function undo(): void
    {
        $result = app(HistoryManager::class)->undo($this->historySnapshots, $this->historyCursor);

        if ($result === null) {
            return;
        }

        $this->historyCursor = $result['cursor'];
        $this->applySnapshot($result['snapshot']);
    }

    public function redo(): void
    {
        $result = app(HistoryManager::class)->redo($this->historySnapshots, $this->historyCursor);

        if ($result === null) {
            return;
        }

        $this->historyCursor = $result['cursor'];
        $this->applySnapshot($result['snapshot']);
    }

    public function insertVariable(string $path, string $variable): void
    {
        if (! in_array($variable, $this->availableVariables, true)) {
            return;
        }

        $currentValue = data_get($this, $path);

        if (! is_string($currentValue)) {
            return;
        }

        $placeholder = '{{ '.$variable.' }}';
        $glue = trim($currentValue) === '' ? '' : ' ';

        data_set($this, $path, $currentValue.$glue.$placeholder);
    }

    public function continueToBuilder(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'theme.content_width' => ['required', 'integer', 'min:480', 'max:760'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'html_content' => $this->currentHtmlContent(),
            'builder_schema' => $this->mode === 'visual' ? $this->currentSchema() : null,
            'is_active' => false,
        ];

        if ($this->templateId === null) {
            $this->template = EmailTemplate::query()->create(array_merge($attributes, ['version' => 1]));
            $this->templateId = $this->template->id;
            $this->isEditing = true;
        } else {
            $this->template = EmailTemplate::query()->findOrFail($this->templateId);
            $this->template->update($attributes);
        }

        $this->currentStep = 2;
    }

    public function backToSetup(): void
    {
        $this->currentStep = 1;
    }

    public function saveTemplate(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'mode' => ['required', 'in:visual,raw'],
            'isActive' => ['required', 'boolean'],
        ];

        if ($this->mode === 'raw') {
            $rules['htmlContent'] = ['required', 'string'];
        }

        if ($this->mode === 'visual') {
            $rules['rows'] = ['required', 'array', 'min:1'];
            $rules['theme.content_width'] = ['required', 'integer', 'min:480', 'max:760'];
        }

        $validated = $this->validate($rules);

        if ($this->mode === 'visual') {
            $this->validateVisualCanvas();
        }

        $attributes = [
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'html_content' => $this->currentHtmlContent(),
            'builder_schema' => $this->mode === 'visual' ? $this->currentSchema() : null,
            'is_active' => $validated['isActive'],
        ];

        if ($this->templateId === null) {
            EmailTemplate::query()->create(array_merge($attributes, ['version' => 1]));
            session()->flash('status', __('Template created successfully.'));
        } else {
            $template = EmailTemplate::query()->findOrFail($this->templateId);

            $template->update(array_merge($attributes, [
                'version' => $template->version + 1,
            ]));

            session()->flash('status', __('Template updated successfully.'));
        }

        $this->redirect(route('templates.index'), navigate: true);
    }

    /**
     * @return array<string, scalar>
     */
    public function previewVariables(): array
    {
        return [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'company' => 'Acme',
            'unsubscribe_url' => 'https://example.com/unsubscribe',
        ];
    }

    public function previewSubject(): string
    {
        return app(TemplateRenderer::class)->render($this->subject, $this->previewVariables());
    }

    public function previewHtml(): string
    {
        return app(TemplateRenderer::class)->render($this->currentHtmlContent(), $this->previewVariables());
    }

    /**
     * @return array{schema_version: int, meta: array<string, string>, theme: array<string, string|int>, rows: array<int, array<string, mixed>>}
     */
    protected function currentSchema(): array
    {
        return [
            'schema_version' => 2,
            'meta' => [
                'template_name' => $this->name,
                'template_key' => $this->templateKey,
            ],
            'theme' => $this->theme,
            'rows' => $this->rows,
        ];
    }

    protected function currentHtmlContent(): string
    {
        if ($this->mode === 'raw') {
            return $this->htmlContent;
        }

        return app(EmailTemplateBuilderRenderer::class)->render($this->currentSchema());
    }

    protected function validateVisualCanvas(): void
    {
        $messages = [];
        $elementCount = 0;

        foreach ($this->rows as $rowIndex => $row) {
            $columns = is_array($row['columns'] ?? null) ? $row['columns'] : [];

            foreach ($columns as $columnIndex => $column) {
                $elements = is_array($column['elements'] ?? null) ? $column['elements'] : [];

                foreach ($elements as $elementIndex => $element) {
                    $elementCount++;
                    $type = (string) ($element['type'] ?? '');
                    $content = is_array($element['content'] ?? null) ? $element['content'] : [];
                    $basePath = "rows.$rowIndex.columns.$columnIndex.elements.$elementIndex.content";

                    if ($type === 'text' && trim((string) ($content['text'] ?? '')) === '') {
                        $messages["$basePath.text"] = __('Text content is required.');
                    }

                    if ($type === 'image' && ! $this->isValidUrlOrPlaceholder((string) ($content['url'] ?? ''))) {
                        $messages["$basePath.url"] = __('Please enter a valid image URL.');
                    }

                    if ($type === 'button') {
                        if (trim((string) ($content['text'] ?? '')) === '') {
                            $messages["$basePath.text"] = __('Button text is required.');
                        }

                        if (! $this->isValidUrlOrPlaceholder((string) ($content['url'] ?? ''))) {
                            $messages["$basePath.url"] = __('Please enter a valid button URL.');
                        }
                    }

                    if ($type === 'social') {
                        $items = is_array($content['items'] ?? null) ? $content['items'] : [];

                        foreach ($items as $itemIndex => $item) {
                            if (! is_array($item)) {
                                continue;
                            }

                            $url = (string) ($item['url'] ?? '');

                            if ($url !== '' && ! $this->isValidUrlOrPlaceholder($url)) {
                                $messages["$basePath.items.$itemIndex.url"] = __('Please enter a valid social URL.');
                            }
                        }
                    }
                }
            }
        }

        if ($elementCount === 0) {
            $messages['rows'] = __('Add at least one element before saving.');
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected function captureHistory(): void
    {
        if ($this->mode !== 'visual') {
            return;
        }

        $result = app(HistoryManager::class)->record(
            history: $this->historySnapshots,
            cursor: $this->historyCursor,
            snapshot: [
                'rows' => $this->rows,
                'theme' => $this->theme,
                'template_key' => $this->templateKey,
            ],
        );

        $this->historySnapshots = $result['history'];
        $this->historyCursor = $result['cursor'];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function applySnapshot(array $snapshot): void
    {
        $this->skipHistoryCapture = true;
        $this->rows = is_array($snapshot['rows'] ?? null) ? $snapshot['rows'] : [];
        $this->theme = is_array($snapshot['theme'] ?? null) ? $snapshot['theme'] : $this->defaultTheme();
        $this->templateKey = (string) ($snapshot['template_key'] ?? $this->templateKey);
        $this->skipHistoryCapture = false;
        $this->hydrateSelection();
    }

    protected function hydrateSelection(): void
    {
        $this->selectedRowId = $this->rows[0]['id'] ?? null;
        $this->selectedColumnId = $this->rows[0]['columns'][0]['id'] ?? null;
        $this->selectedElementId = $this->rows[0]['columns'][0]['elements'][0]['id'] ?? null;
    }

    protected function applyStarterTemplate(string $templateKey): void
    {
        $templates = $this->starterTemplates();

        if (! isset($templates[$templateKey])) {
            $templateKey = 'welcome';
        }

        $template = $templates[$templateKey];

        $this->templateKey = $templateKey;
        $this->subject = $template['subject'];
        $this->theme = array_replace($this->defaultTheme(), $template['theme']);
        $this->rows = $this->normalizeRows($template['rows']);
        $this->hydrateSelection();
    }

    /**
     * @return array<string, array{label: string, subject: string, theme: array<string, string|int>, rows: array<int, array<string, mixed>>}>
     */
    protected function starterTemplates(): array
    {
        return [
            'welcome' => [
                'label' => 'Welcome',
                'subject' => 'Welcome {{ first_name }}',
                'theme' => [],
                'rows' => [
                    [
                        'columns' => [
                            [
                                'width' => '100%',
                                'elements' => [
                                    ['type' => 'text', 'content' => ['text' => 'Welcome {{ first_name }}!']],
                                    ['type' => 'image', 'content' => ['url' => $this->placeholderImageUrl('hero'), 'alt' => 'Welcome image']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'promo' => [
                'label' => 'Promo',
                'subject' => 'Special offer for {{ first_name }}',
                'theme' => ['button_bg_color' => '#0f766e', 'link_color' => '#0f766e'],
                'rows' => [
                    [
                        'columns' => [
                            [
                                'width' => '100%',
                                'elements' => [
                                    ['type' => 'text', 'content' => ['text' => 'Save 30% this week only.']],
                                    ['type' => 'button', 'content' => ['text' => 'Claim offer', 'url' => 'https://example.com/offer']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'newsletter' => [
                'label' => 'Newsletter',
                'subject' => 'Weekly update for {{ first_name }}',
                'theme' => [],
                'rows' => [
                    [
                        'columns' => [
                            [
                                'width' => '50%',
                                'elements' => [
                                    ['type' => 'image', 'content' => ['url' => $this->placeholderImageUrl('feature'), 'alt' => 'Feature image']],
                                ],
                            ],
                            [
                                'width' => '50%',
                                'elements' => [
                                    ['type' => 'text', 'content' => ['text' => 'Key updates from {{ company }} this week.']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'blank' => [
                'label' => 'Blank',
                'subject' => 'Hello {{ first_name }}',
                'theme' => [],
                'rows' => [
                    [
                        'columns' => [
                            [
                                'width' => '100%',
                                'elements' => [
                                    ['type' => 'text', 'content' => ['text' => 'Start designing your email...']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeRows(array $rows): array
    {
        return collect($rows)->map(function (array $row): array {
            return [
                'id' => (string) ($row['id'] ?? (string) str()->ulid()),
                'style' => is_array($row['style'] ?? null) ? $row['style'] : [],
                'columns' => collect((array) ($row['columns'] ?? []))
                    ->map(function (array $column): array {
                        return [
                            'id' => (string) ($column['id'] ?? (string) str()->ulid()),
                            'width' => in_array((string) ($column['width'] ?? '100%'), ['50%', '100%'], true)
                                ? (string) ($column['width'] ?? '100%')
                                : '100%',
                            'elements' => collect((array) ($column['elements'] ?? []))
                                ->map(function (array $element): array {
                                    $type = (string) ($element['type'] ?? 'text');

                                    return [
                                        'id' => (string) ($element['id'] ?? (string) str()->ulid()),
                                        'type' => $type,
                                        'content' => $this->sanitizeElementContent(
                                            $type,
                                            array_replace($this->defaultElementContent($type), (array) ($element['content'] ?? [])),
                                        ),
                                        'style' => is_array($element['style'] ?? null) ? $element['style'] : [],
                                        'visibility' => array_replace(['desktop' => true, 'mobile' => true], (array) ($element['visibility'] ?? [])),
                                    ];
                                })
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    /**
     * @return array{id: string, type: string, content: array<string, mixed>, style: array<string, mixed>, visibility: array<string, bool>}
     */
    protected function newElement(string $type): array
    {
        return [
            'id' => (string) str()->ulid(),
            'type' => $type,
            'content' => $this->defaultElementContent($type),
            'style' => [],
            'visibility' => ['desktop' => true, 'mobile' => true],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultElementContent(string $type): array
    {
        return match ($type) {
            'text' => ['text' => 'Write your message here...'],
            'image' => ['url' => $this->placeholderImageUrl(), 'alt' => 'Image'],
            'button' => ['text' => 'Open link', 'url' => 'https://example.com'],
            'spacer' => ['height' => 24],
            'social' => ['items' => [
                ['label' => 'Website', 'url' => 'https://example.com'],
                ['label' => 'LinkedIn', 'url' => 'https://linkedin.com'],
            ]],
            default => [],
        };
    }

    protected function placeholderImageUrl(string $variant = 'default'): string
    {
        return match ($variant) {
            'hero' => 'https://placehold.co/1200x640/F1F5F9/0F172A?text=Add+Your+Hero+Image',
            'feature' => 'https://placehold.co/900x900/E2E8F0/0F172A?text=Feature+Image',
            default => 'https://placehold.co/1200x675/F8FAFC/334155?text=Replace+with+your+image',
        };
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function sanitizeElementContent(string $type, array $content): array
    {
        if ($type !== 'image') {
            return $content;
        }

        $legacyStarterUrls = [
            'https://images.unsplash.com/photo-1557682250-33bd709cbe85?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80',
        ];

        if (in_array((string) ($content['url'] ?? ''), $legacyStarterUrls, true)) {
            $content['url'] = $this->placeholderImageUrl();
        }

        return $content;
    }

    /**
     * @return array<string, string|int>
     */
    protected function defaultTheme(): array
    {
        return [
            'content_width' => 640,
            'font_family' => 'Arial, sans-serif',
            'background_color' => '#f3f4f6',
            'surface_color' => '#ffffff',
            'text_color' => '#1f2937',
            'heading_color' => '#111827',
            'link_color' => '#2563eb',
            'button_bg_color' => '#2563eb',
            'button_text_color' => '#ffffff',
            'section_spacing' => 24,
        ];
    }

    protected function isValidUrlOrPlaceholder(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^\{\{\s*[a-zA-Z0-9_]+\s*\}\}$/', $trimmed) === 1) {
            return true;
        }

        return filter_var($trimmed, FILTER_VALIDATE_URL) !== false;
    }

    protected function resolveTemplate(EmailTemplate|int|string|null $template): ?EmailTemplate
    {
        if ($template instanceof EmailTemplate) {
            return $template;
        }

        if (is_numeric($template)) {
            return EmailTemplate::query()->findOrFail((int) $template);
        }

        return null;
    }

    public function render(): View
    {
        return view('livewire.templates.builder-page');
    }
}
