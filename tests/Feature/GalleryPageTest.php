<?php

use App\Http\Requests\PresignGalleryAssetsRequest;
use App\Models\MediaAsset;
use App\Models\User;
use Livewire\Livewire;

it('shows gallery page and sidebar item for authenticated users', function (): void {
    config()->set('filesystems.disks.s3.url', 'https://cdn.example.com');

    $this->actingAs(User::factory()->create());

    $this->get(route('gallery.index'))
        ->assertOk()
        ->assertSee('Gallery')
        ->assertSee('Upload files')
        ->assertSee(route('gallery.index'), false)
        ->assertSee('data-max-size-bytes="'.PresignGalleryAssetsRequest::MAX_FILE_SIZE_BYTES.'"', false)
        ->assertSee('application/pdf')
        ->assertDontSee('S3 public base URL is not configured');
});

it('shows guidance when s3 public base url is missing', function (): void {
    config()->set('filesystems.disks.s3.url', null);

    $this->actingAs(User::factory()->create());

    $this->get(route('gallery.index'))
        ->assertOk()
        ->assertSee('S3 public base URL is not configured');
});

it('lists uploaded assets on gallery page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    MediaAsset::query()->create([
        'user_id' => $user->id,
        'external_id' => '018f47ce-c10e-7000-8b3b-a11223344556',
        'original_name' => 'hero-banner.png',
        'storage_disk' => 's3',
        'storage_path' => 'gallery-assets/hero-banner.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'size_bytes' => 2048,
        'public_url' => 'https://cdn.example.com/gallery-assets/hero-banner.png',
        'kind' => MediaAsset::KIND_IMAGE,
    ]);

    Livewire::test('pages::gallery.index')
        ->assertSee('hero-banner.png')
        ->assertSee('Image')
        ->assertSee('Copy URL');
});

it('filters gallery assets by kind', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    MediaAsset::query()->create([
        'user_id' => $user->id,
        'original_name' => 'visual.png',
        'storage_disk' => 's3',
        'storage_path' => 'gallery-assets/visual.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'size_bytes' => 111,
        'public_url' => 'https://cdn.example.com/gallery-assets/visual.png',
        'kind' => MediaAsset::KIND_IMAGE,
    ]);

    MediaAsset::query()->create([
        'user_id' => $user->id,
        'original_name' => 'brochure.pdf',
        'storage_disk' => 's3',
        'storage_path' => 'gallery-assets/brochure.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 222,
        'public_url' => 'https://cdn.example.com/gallery-assets/brochure.pdf',
        'kind' => MediaAsset::KIND_PDF,
    ]);

    Livewire::test('pages::gallery.index')
        ->set('kindFilter', MediaAsset::KIND_IMAGE)
        ->assertSee('visual.png')
        ->assertDontSee('brochure.pdf')
        ->set('kindFilter', MediaAsset::KIND_PDF)
        ->assertSee('brochure.pdf')
        ->assertDontSee('visual.png');
});

it('searches gallery assets by original name and external id', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    MediaAsset::query()->create([
        'user_id' => $user->id,
        'external_id' => '018f47ce-c10e-7000-8b3b-abcdefabcdef',
        'original_name' => 'search-target-image.png',
        'storage_disk' => 's3',
        'storage_path' => 'gallery-assets/search-target-image.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'size_bytes' => 333,
        'public_url' => 'https://cdn.example.com/gallery-assets/search-target-image.png',
        'kind' => MediaAsset::KIND_IMAGE,
    ]);

    MediaAsset::query()->create([
        'user_id' => $user->id,
        'external_id' => '018f47ce-c10e-7000-8b3b-001122334455',
        'original_name' => 'unrelated.pdf',
        'storage_disk' => 's3',
        'storage_path' => 'gallery-assets/unrelated.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 444,
        'public_url' => 'https://cdn.example.com/gallery-assets/unrelated.pdf',
        'kind' => MediaAsset::KIND_PDF,
    ]);

    Livewire::test('pages::gallery.index')
        ->set('search', 'target-image')
        ->assertSee('search-target-image.png')
        ->assertDontSee('unrelated.pdf')
        ->set('search', '001122334455')
        ->assertSee('unrelated.pdf')
        ->assertDontSee('search-target-image.png');
});

it('trashes and restores assets from gallery page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $asset = MediaAsset::query()->create([
        'user_id' => $user->id,
        'original_name' => 'soft-delete-me.pdf',
        'storage_disk' => 's3',
        'storage_path' => 'gallery-assets/soft-delete-me.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 555,
        'public_url' => 'https://cdn.example.com/gallery-assets/soft-delete-me.pdf',
        'kind' => MediaAsset::KIND_PDF,
    ]);

    Livewire::test('pages::gallery.index')
        ->call('trash', $asset->id)
        ->assertHasNoErrors()
        ->set('statusFilter', 'trashed')
        ->assertSee('soft-delete-me.pdf')
        ->call('restore', $asset->id)
        ->assertHasNoErrors()
        ->set('statusFilter', 'active')
        ->assertSee('soft-delete-me.pdf');
});
