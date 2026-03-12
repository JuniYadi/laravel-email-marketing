<?php

use App\Models\LandingPageTemplate;
use App\Models\User;
use Livewire\Livewire;

it('adds repeater item defaults in landing page editor', function () {
    $this->actingAs(User::factory()->create());

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                [
                    'key' => 'cards',
                    'label' => 'Cards',
                    'type' => 'repeater',
                    'required' => true,
                    'fields' => [
                        ['key' => 'order', 'label' => 'Order', 'type' => 'number', 'required' => true, 'default' => 1],
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'default' => ''],
                        ['key' => 'content', 'label' => 'Content', 'type' => 'richtext', 'required' => true, 'default' => ''],
                    ],
                ],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->call('addRepeaterItem', 'cards')
        ->assertSet('formData.cards.0.order', 1)
        ->assertSet('formData.cards.0.title', '')
        ->assertSet('formData.cards.0.content', '');
});

it('removes repeater items in landing page editor', function () {
    $this->actingAs(User::factory()->create());

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                [
                    'key' => 'cards',
                    'label' => 'Cards',
                    'type' => 'repeater',
                    'required' => true,
                    'default' => [
                        ['order' => 1, 'title' => 'Card 1', 'content' => '<p>One</p>'],
                        ['order' => 2, 'title' => 'Card 2', 'content' => '<p>Two</p>'],
                    ],
                    'fields' => [
                        ['key' => 'order', 'label' => 'Order', 'type' => 'number', 'required' => true, 'default' => 1],
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'default' => ''],
                        ['key' => 'content', 'label' => 'Content', 'type' => 'richtext', 'required' => true, 'default' => ''],
                    ],
                ],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->call('removeRepeaterItem', 'cards', 0)
        ->assertSet('formData.cards.0.title', 'Card 2');
});

it('renders trix editor for repeater richtext fields in landing page editor', function () {
    $this->actingAs(User::factory()->create());

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                [
                    'key' => 'cards',
                    'label' => 'Cards',
                    'type' => 'repeater',
                    'required' => true,
                    'default' => [
                        ['order' => 1, 'title' => 'Card 1', 'content' => '<p>One</p>'],
                    ],
                    'fields' => [
                        ['key' => 'order', 'label' => 'Order', 'type' => 'number', 'required' => true, 'default' => 1],
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'default' => ''],
                        ['key' => 'content', 'label' => 'Content', 'type' => 'richtext', 'required' => true, 'default' => ''],
                    ],
                ],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->assertSee('trix-editor', false)
        ->assertSee('landing-page-richtext-repeater-', false);
});

it('renders a compact preview image in landing page editor image upload field', function () {
    $this->actingAs(User::factory()->create());

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                [
                    'key' => 'hero_image',
                    'label' => 'Hero Image',
                    'type' => 'image_url',
                    'required' => true,
                    'default' => 'https://example.com/image.png',
                ],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->assertSee('h-24 max-h-24 overflow-hidden', false)
        ->assertSee('h-full max-h-24 w-full object-contain', false)
        ->assertDontSee('h-40 w-full object-cover', false);
});
