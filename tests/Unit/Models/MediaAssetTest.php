<?php

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates external id and stores media metadata', function () {
    $user = User::factory()->create();

    $asset = MediaAsset::query()->create([
        'user_id' => $user->id,
        'original_name' => 'promo-banner.png',
        'storage_disk' => 's3',
        'storage_path' => 'gallery-assets/abc123-promo-banner.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'size_bytes' => 2048,
        'public_url' => 'https://cdn.example.com/gallery-assets/abc123-promo-banner.png',
        'kind' => MediaAsset::KIND_IMAGE,
    ]);

    expect($asset->external_id)
        ->not->toBe('')
        ->and(Str::isUuid($asset->external_id))->toBeTrue()
        ->and($asset->kind)->toBe(MediaAsset::KIND_IMAGE);
});

it('filters media assets by active, trashed, kind, and search scopes', function () {
    $user = User::factory()->create();

    $image = MediaAsset::query()->create([
        'user_id' => $user->id,
        'external_id' => (string) Str::uuid7(),
        'original_name' => 'hero-banner.png',
        'storage_disk' => 's3',
        'storage_path' => 'gallery-assets/hero-banner.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'size_bytes' => 100,
        'public_url' => 'https://cdn.example.com/gallery-assets/hero-banner.png',
        'kind' => MediaAsset::KIND_IMAGE,
    ]);

    $pdf = MediaAsset::query()->create([
        'user_id' => $user->id,
        'external_id' => (string) Str::uuid7(),
        'original_name' => 'brochure.pdf',
        'storage_disk' => 's3',
        'storage_path' => 'gallery-assets/brochure.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 200,
        'public_url' => 'https://cdn.example.com/gallery-assets/brochure.pdf',
        'kind' => MediaAsset::KIND_PDF,
    ]);

    $pdf->delete();

    $activeIds = MediaAsset::query()->active()->pluck('id')->all();
    $trashedIds = MediaAsset::query()->trashed()->pluck('id')->all();
    $imageIds = MediaAsset::query()->kind(MediaAsset::KIND_IMAGE)->pluck('id')->all();
    $searchIds = MediaAsset::query()->search('hero')->pluck('id')->all();

    expect($activeIds)->toContain($image->id)
        ->and($activeIds)->not->toContain($pdf->id)
        ->and($trashedIds)->toContain($pdf->id)
        ->and($imageIds)->toContain($image->id)
        ->and($searchIds)->toContain($image->id)
        ->and($searchIds)->not->toContain($pdf->id);
});
