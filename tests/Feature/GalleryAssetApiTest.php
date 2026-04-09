<?php

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('filesystems.disks.s3.driver', 's3');
    config()->set('filesystems.disks.s3.url', 'https://cdn.example.com');
});

it('requires authentication for gallery page', function (): void {
    $this->get(route('gallery.index'))
        ->assertRedirect(route('login'));
});

it('returns presigned uploads for allowed image and pdf files', function (): void {
    $this->actingAs(User::factory()->create());

    $mockedDisk = \Mockery::mock(FilesystemAdapter::class);
    $mockedDisk->shouldReceive('temporaryUploadUrl')
        ->twice()
        ->andReturn([
            'url' => 'https://s3.example.com/upload',
            'headers' => ['Content-Type' => 'application/octet-stream'],
        ]);

    Storage::shouldReceive('disk')
        ->twice()
        ->with('s3')
        ->andReturn($mockedDisk);

    $response = $this->postJson(route('gallery.assets.presign'), [
        'files' => [
            [
                'name' => 'banner.png',
                'size' => 1024,
                'mime_type' => 'image/png',
            ],
            [
                'name' => 'catalog.pdf',
                'size' => 2048,
                'mime_type' => 'application/pdf',
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonCount(2, 'uploads')
        ->assertJsonPath('uploads.0.disk', 's3')
        ->assertJsonPath('uploads.0.method', 'PUT')
        ->assertJsonPath('uploads.0.mime_type', 'image/png')
        ->assertJsonPath('uploads.1.mime_type', 'application/pdf');
});

it('returns validation error when presign temporary upload url generation fails', function (): void {
    $this->actingAs(User::factory()->create());

    $mockedDisk = \Mockery::mock(FilesystemAdapter::class);
    $mockedDisk->shouldReceive('temporaryUploadUrl')
        ->once()
        ->andThrow(new \RuntimeException('temporary upload unsupported'));

    Storage::shouldReceive('disk')
        ->once()
        ->with('s3')
        ->andReturn($mockedDisk);

    $this->postJson(route('gallery.assets.presign'), [
        'files' => [
            [
                'name' => 'banner.png',
                'size' => 1024,
                'mime_type' => 'image/png',
            ],
        ],
    ])->assertUnprocessable()->assertInvalid(['files']);
});

it('rejects disallowed mime types on presign', function (): void {
    $this->actingAs(User::factory()->create());

    $this->postJson(route('gallery.assets.presign'), [
        'files' => [
            [
                'name' => 'virus.exe',
                'size' => 1024,
                'mime_type' => 'application/x-msdownload',
            ],
        ],
    ])->assertUnprocessable()->assertInvalid(['files.0.mime_type']);
});

it('finalizes upload and persists media asset metadata', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Storage::fake('s3');
    Storage::disk('s3')->put('gallery-assets/finalize-banner.png', str_repeat('a', 16));

    $response = $this->postJson(route('gallery.assets.finalize'), [
        'uploads' => [
            [
                'name' => 'finalize-banner.png',
                'path' => 'gallery-assets/finalize-banner.png',
                'disk' => 's3',
                'mime_type' => 'image/png',
                'size' => 16,
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('assets.0.storage_path', 'gallery-assets/finalize-banner.png')
        ->assertJsonPath('assets.0.kind', MediaAsset::KIND_IMAGE);

    $asset = MediaAsset::query()->first();

    expect($asset)
        ->not->toBeNull()
        ->and($asset?->user_id)->toBe($user->id)
        ->and($asset?->kind)->toBe(MediaAsset::KIND_IMAGE)
        ->and($asset?->public_url)->toBe('https://cdn.example.com/gallery-assets/finalize-banner.png');
});

it('rejects finalize when object is missing', function (): void {
    $this->actingAs(User::factory()->create());

    Storage::fake('s3');

    $this->postJson(route('gallery.assets.finalize'), [
        'uploads' => [
            [
                'name' => 'missing.pdf',
                'path' => 'gallery-assets/missing.pdf',
                'disk' => 's3',
                'mime_type' => 'application/pdf',
                'size' => 10,
            ],
        ],
    ])->assertUnprocessable()->assertInvalid(['uploads']);
});

it('rejects finalize for unsafe gallery path', function (): void {
    $this->actingAs(User::factory()->create());

    Storage::fake('s3');
    Storage::disk('s3')->put('gallery-assets/unsafe.png', 'abc');

    $this->postJson(route('gallery.assets.finalize'), [
        'uploads' => [
            [
                'name' => 'unsafe.png',
                'path' => '../gallery-assets/unsafe.png',
                'disk' => 's3',
                'mime_type' => 'image/png',
                'size' => 3,
            ],
        ],
    ])->assertUnprocessable()->assertInvalid(['uploads']);
});

it('resolves pdf kind and falls back to disk url when configured base url is empty', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    config()->set('filesystems.disks.s3.url', null);
    Storage::fake('s3');
    Storage::disk('s3')->put('gallery-assets/manual.pdf', str_repeat('b', 8));

    $response = $this->postJson(route('gallery.assets.finalize'), [
        'uploads' => [
            [
                'name' => 'manual.pdf',
                'path' => 'gallery-assets/manual.pdf',
                'disk' => 's3',
                'mime_type' => 'application/pdf',
                'size' => 8,
            ],
        ],
    ]);

    $response->assertOk();

    $asset = MediaAsset::query()->firstOrFail();

    expect($asset->kind)->toBe(MediaAsset::KIND_PDF)
        ->and($asset->public_url)->toContain('/gallery-assets/manual.pdf');
});

it('moves asset to trash and restores it', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $asset = MediaAsset::query()->create([
        'user_id' => $user->id,
        'original_name' => 'trash-me.pdf',
        'storage_disk' => 's3',
        'storage_path' => 'gallery-assets/trash-me.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 250,
        'public_url' => 'https://cdn.example.com/gallery-assets/trash-me.pdf',
        'kind' => MediaAsset::KIND_PDF,
    ]);

    $this->deleteJson(route('gallery.assets.trash', $asset))
        ->assertOk()
        ->assertJsonPath('trashed', true);

    expect(MediaAsset::query()->find($asset->id))->toBeNull();
    expect(MediaAsset::withTrashed()->find($asset->id)?->trashed())->toBeTrue();

    $this->patchJson(route('gallery.assets.restore', $asset->id))
        ->assertOk()
        ->assertJsonPath('restored', true);

    expect(MediaAsset::query()->find($asset->id))->not->toBeNull();
});
