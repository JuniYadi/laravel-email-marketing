<?php

use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('stores uploaded og images on s3 and persists the public url', function () {
    config()->set('filesystems.disks.s3.url', 'https://cdn.example.com');
    Storage::fake('s3');

    actingAs(User::factory()->create());

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->set('title', 'S3 Meta Image')
        ->set('slug', 's3-meta-image')
        ->set('meta.title', 'S3 Meta Image')
        ->set('formData.headline', 'Hero headline')
        ->set('metaOgImageUpload', UploadedFile::fake()->image('og-banner.png'))
        ->call('saveDraft')
        ->assertHasNoErrors();

    $landingPage = LandingPage::query()->where('slug', 's3-meta-image')->firstOrFail();
    $ogImageUrl = (string) data_get($landingPage->meta, 'og_image');

    expect($ogImageUrl)->toStartWith('https://cdn.example.com/landing-page-images/');

    $storedPath = ltrim((string) str($ogImageUrl)->after('https://cdn.example.com/'), '/');
    expect(Storage::disk('s3')->exists($storedPath))->toBeTrue();
});

it('stores uploaded template images on s3 and saves the generated public url', function () {
    config()->set('filesystems.disks.s3.url', 'https://cdn.example.com');
    Storage::fake('s3');

    actingAs(User::factory()->create());

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                ['key' => 'hero_image', 'label' => 'Hero Image', 'type' => 'image_url', 'required' => false],
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->set('title', 'S3 Template Image')
        ->set('slug', 's3-template-image')
        ->set('meta.title', 'S3 Template Image')
        ->set('formData.headline', 'Hero headline')
        ->set('imageUploads.hero_image', UploadedFile::fake()->image('hero.png'))
        ->call('saveDraft')
        ->assertHasNoErrors();

    $landingPage = LandingPage::query()->where('slug', 's3-template-image')->firstOrFail();
    $heroImageUrl = (string) data_get($landingPage->form_data, 'hero_image');

    expect($heroImageUrl)->toStartWith('https://cdn.example.com/landing-page-images/');

    $storedPath = ltrim((string) str($heroImageUrl)->after('https://cdn.example.com/'), '/');
    expect(Storage::disk('s3')->exists($storedPath))->toBeTrue();
});

it('rejects non-image uploads for landing page image fields', function () {
    Storage::fake('s3');

    actingAs(User::factory()->create());

    $template = LandingPageTemplate::factory()->create([
        'schema' => [
            'fields' => [
                ['key' => 'hero_image', 'label' => 'Hero Image', 'type' => 'image_url', 'required' => false],
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'required' => true],
            ],
        ],
    ]);

    Livewire::test('pages::landing-pages.editor')
        ->set('selectedTemplateId', $template->id)
        ->set('title', 'Invalid Upload')
        ->set('slug', 'invalid-upload')
        ->set('meta.title', 'Invalid Upload')
        ->set('formData.headline', 'Hero headline')
        ->set('imageUploads.hero_image', UploadedFile::fake()->create('brochure.pdf', 100, 'application/pdf'))
        ->assertHasErrors(['imageUploads.hero_image']);
});
