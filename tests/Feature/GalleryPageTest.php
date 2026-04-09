<?php

use App\Http\Requests\PresignGalleryAssetsRequest;
use App\Models\MediaAsset;
use App\Models\User;
use Livewire\Livewire;

it('shows gallery page and sidebar item for authenticated users', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(route('gallery.index'))
        ->assertOk()
        ->assertSee('Gallery')
        ->assertSee('Upload files')
        ->assertSee(route('gallery.index'), false)
        ->assertSee('data-max-size-bytes="'.PresignGalleryAssetsRequest::MAX_FILE_SIZE_BYTES.'"', false)
        ->assertSee('application/pdf');
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
