<?php

use App\Livewire\Templates\BuilderPage;
use App\Mail\TemplateTestMail;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('requires authentication for templates page', function () {
    $this->get(route('templates.index'))
        ->assertRedirect(route('login'));
});

it('requires authentication for template create page', function () {
    $this->get(route('templates.create'))
        ->assertRedirect(route('login'));
});

it('requires authentication for template edit page', function () {
    $template = EmailTemplate::factory()->create();

    $this->get(route('templates.edit', $template))
        ->assertRedirect(route('login'));
});

it('renders template create page for authenticated users', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('templates.create'))
        ->assertSuccessful()
        ->assertSee('Create Template')
        ->assertSee('Step 1 of 2')
        ->assertSee('Continue to Builder')
        ->assertDontSee('Rows');
});

it('starts create flow on setup step and hides canvas interactions', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BuilderPage::class)
        ->assertSet('currentStep', 1)
        ->assertSee('Continue to Builder')
        ->assertDontSee('Drop row preset here to insert at top')
        ->assertDontSee('wire:click.self="selectRow(', false)
        ->assertDontSee('wire:click.stop="selectColumn(', false)
        ->assertDontSee('wire:click.stop="selectElement(', false);
});

it('continues from setup step and creates template draft', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BuilderPage::class)
        ->set('name', 'Setup Draft')
        ->set('subject', 'Subject {{ first_name }}')
        ->set('theme.content_width', 640)
        ->call('continueToBuilder')
        ->assertSet('currentStep', 2)
        ->assertSet('isEditing', true)
        ->assertSet('templateId', fn (?int $value): bool => $value !== null);

    $template = EmailTemplate::query()->where('name', 'Setup Draft')->first();

    expect($template)->not->toBeNull();
    expect($template?->subject)->toBe('Subject {{ first_name }}');
    expect($template?->is_active)->toBeFalse();
});

it('opens existing template on build step and can go back to setup', function () {
    $this->actingAs(User::factory()->create());

    $template = EmailTemplate::factory()->create();

    Livewire::test(BuilderPage::class, ['template' => $template])
        ->assertSet('currentStep', 2)
        ->call('backToSetup')
        ->assertSet('currentStep', 1);
});

it('downloads templates as csv', function () {
    $this->actingAs(User::factory()->create());

    EmailTemplate::factory()->create();

    Livewire::test('pages::templates.index')
        ->call('exportCsv')
        ->assertFileDownloaded();
});

it('creates visual template without button element and stores schema v2', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BuilderPage::class)
        ->set('mode', 'visual')
        ->set('name', 'No Button Template')
        ->set('subject', 'Welcome {{ first_name }}')
        ->set('rows', [
            [
                'id' => 'row-1',
                'columns' => [
                    [
                        'id' => 'col-1',
                        'width' => '100%',
                        'elements' => [
                            [
                                'id' => 'element-1',
                                'type' => 'text',
                                'content' => [
                                    'text' => 'Hello {{ first_name }} from {{ company }}',
                                ],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                        ],
                    ],
                ],
                'style' => [],
            ],
        ])
        ->set('isActive', true)
        ->call('saveTemplate')
        ->assertHasNoErrors()
        ->assertRedirect(route('templates.index'));

    $template = EmailTemplate::query()->latest('id')->first();

    expect($template)->not->toBeNull();
    expect($template?->builder_schema)->toBeArray();
    expect($template?->builder_schema['schema_version'])->toBe(2);
    expect($template?->builder_schema['rows'][0]['columns'][0]['elements'][0]['type'])->toBe('text');
    expect($template?->html_content)->toContain('Hello {{ first_name }} from {{ company }}');
    expect($template?->html_content)->not->toContain('Get Started');
});

it('removes button element and keeps template valid', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BuilderPage::class)
        ->set('mode', 'visual')
        ->set('rows', [
            [
                'id' => 'row-1',
                'columns' => [
                    [
                        'id' => 'col-1',
                        'width' => '100%',
                        'elements' => [
                            [
                                'id' => 'text-1',
                                'type' => 'text',
                                'content' => ['text' => 'Main copy'],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                            [
                                'id' => 'button-1',
                                'type' => 'button',
                                'content' => ['text' => 'Open', 'url' => 'https://example.com'],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                        ],
                    ],
                ],
                'style' => [],
            ],
        ])
        ->call('removeElement', 'button-1')
        ->assertSet('rows.0.columns.0.elements.0.id', 'text-1')
        ->assertSet('rows.0.columns.0.elements', fn (array $elements): bool => count($elements) === 1);
});

it('moves element across columns with explicit move handler', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BuilderPage::class)
        ->set('rows', [
            [
                'id' => 'row-1',
                'columns' => [
                    [
                        'id' => 'col-left',
                        'width' => '50%',
                        'elements' => [
                            [
                                'id' => 'element-a',
                                'type' => 'text',
                                'content' => ['text' => 'From left'],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                        ],
                    ],
                    [
                        'id' => 'col-right',
                        'width' => '50%',
                        'elements' => [],
                    ],
                ],
                'style' => [],
            ],
        ])
        ->call('moveElement', 'element-a', 'row-1', 'col-right', 0)
        ->assertSet('rows.0.columns.0.elements', [])
        ->assertSet('rows.0.columns.1.elements.0.id', 'element-a');
});

it('supports undo and redo for canvas mutations', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BuilderPage::class)
        ->set('rows', [])
        ->call('addRowPreset', 1)
        ->assertSet('rows', fn (array $rows): bool => count($rows) === 1)
        ->call('undo')
        ->assertSet('rows', [])
        ->call('redo')
        ->assertSet('rows', fn (array $rows): bool => count($rows) === 1);
});

it('migrates schema v1 templates into schema v2 canvas', function () {
    $this->actingAs(User::factory()->create());

    $template = EmailTemplate::factory()->create([
        'builder_schema' => [
            'schema_version' => 1,
            'template_key' => 'welcome',
            'theme' => [],
            'blocks' => [
                ['id' => 'legacy-text', 'type' => 'text', 'content' => ['text' => 'Legacy message']],
            ],
        ],
    ]);

    Livewire::test(BuilderPage::class, ['template' => $template])
        ->assertSet('mode', 'visual')
        ->assertSet('schemaVersion', 2)
        ->assertSet('rows.0.columns.0.elements.0.content.text', 'Legacy message');
});

it('supports raw mode and clears schema on update', function () {
    $this->actingAs(User::factory()->create());

    $template = EmailTemplate::factory()->create([
        'builder_schema' => [
            'schema_version' => 2,
            'meta' => ['template_name' => 'x', 'template_key' => 'blank'],
            'theme' => [],
            'rows' => [],
        ],
    ]);

    Livewire::test(BuilderPage::class, ['template' => $template])
        ->set('mode', 'raw')
        ->set('name', 'Raw Update')
        ->set('subject', 'Subject {{ first_name }}')
        ->set('htmlContent', '<h1>Manual {{ first_name }}</h1>')
        ->set('isActive', true)
        ->call('saveTemplate')
        ->assertHasNoErrors()
        ->assertRedirect(route('templates.index'));

    $template->refresh();

    expect($template->builder_schema)->toBeNull();
    expect($template->name)->toBe('Raw Update');
    expect($template->html_content)->toBe('<h1>Manual {{ first_name }}</h1>');
    expect($template->version)->toBe(2);
});

it('falls back to raw mode for legacy templates without builder schema', function () {
    $this->actingAs(User::factory()->create());

    $template = EmailTemplate::factory()->create([
        'builder_schema' => null,
    ]);

    Livewire::test(BuilderPage::class, ['template' => $template])
        ->assertSet('mode', 'raw')
        ->assertSet('isLegacyTemplate', true);
});

it('stores uploaded image and updates nested image element url', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create());

    Livewire::test(BuilderPage::class)
        ->set('mode', 'visual')
        ->set('rows', [
            [
                'id' => 'row-1',
                'columns' => [
                    [
                        'id' => 'col-1',
                        'width' => '100%',
                        'elements' => [
                            [
                                'id' => 'image-1',
                                'type' => 'image',
                                'content' => [
                                    'url' => 'https://example.com/old.png',
                                    'alt' => 'Old',
                                ],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                        ],
                    ],
                ],
                'style' => [],
            ],
        ])
        ->set('imageUploads.0.0.0', UploadedFile::fake()->image('banner.jpg'))
        ->assertSet('rows.0.columns.0.elements.0.content.url', fn (string $url): bool => str_contains($url, '/storage/email-templates/'));
});

it('uses neutral placeholder images for starter and new image elements', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BuilderPage::class)
        ->assertSet('rows.0.columns.0.elements.1.content.url', fn (string $url): bool => str_contains($url, 'https://placehold.co/1200x640/'))
        ->set('rows', [
            [
                'id' => 'row-1',
                'style' => [],
                'columns' => [
                    [
                        'id' => 'col-1',
                        'width' => '100%',
                        'elements' => [
                            [
                                'id' => 'text-1',
                                'type' => 'text',
                                'content' => ['text' => 'Start'],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->call('addElement', 'row-1', 'col-1', 'image', 2)
        ->assertSet('rows.0.columns.0.elements.1.content.url', fn (string $url): bool => str_contains($url, 'https://placehold.co/1200x675/'));
});

it('normalizes legacy starter image urls when loading existing templates', function () {
    $this->actingAs(User::factory()->create());

    $template = EmailTemplate::factory()->create([
        'builder_schema' => [
            'schema_version' => 2,
            'meta' => ['template_name' => 'Legacy starter', 'template_key' => 'welcome'],
            'theme' => [],
            'rows' => [
                [
                    'id' => 'row-1',
                    'style' => [],
                    'columns' => [
                        [
                            'id' => 'col-1',
                            'width' => '100%',
                            'elements' => [
                                [
                                    'id' => 'image-1',
                                    'type' => 'image',
                                    'content' => [
                                        'url' => 'https://images.unsplash.com/photo-1557682250-33bd709cbe85?auto=format&fit=crop&w=1200&q=80',
                                        'alt' => 'Legacy',
                                    ],
                                    'style' => [],
                                    'visibility' => ['desktop' => true, 'mobile' => true],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    Livewire::test(BuilderPage::class, ['template' => $template])
        ->assertSet('rows.0.columns.0.elements.0.content.url', fn (string $url): bool => str_contains($url, 'https://placehold.co/1200x675/'));
});

it('renders preview modal for selected template', function () {
    $this->actingAs(User::factory()->create());

    $template = EmailTemplate::factory()->create([
        'name' => 'Newsletter',
        'subject' => 'Hello {{ first_name }}',
        'html_content' => '<p>{{ first_name }} from {{ company }}</p>',
    ]);

    Livewire::test('pages::templates.index')
        ->call('openPreviewModal', $template->id)
        ->assertSet('previewTemplateId', $template->id)
        ->assertSet('previewHtml', '<p>Jane from Acme</p>');
});

it('sends a test email from selected template', function () {
    Mail::fake();

    $this->actingAs(User::factory()->create());

    $template = EmailTemplate::factory()->create([
        'subject' => 'Hi {{ first_name }}',
        'html_content' => '<p>Hello {{ first_name }}</p>',
    ]);

    Livewire::test('pages::templates.index')
        ->set('testEmail', 'preview@example.com')
        ->call('sendTestEmail', $template->id)
        ->assertHasNoErrors();

    Mail::assertSent(TemplateTestMail::class, function (TemplateTestMail $mail) {
        return $mail->hasTo('preview@example.com');
    });
});
