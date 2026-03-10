<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('generates presigned upload URL for valid image', function () {
    config()->set('filesystems.disks.s3.url', 'https://cdn.example.com');
    Storage::fake('s3');

    $response = $this->postJson(route('landing-pages.images.presign'), [
        'file_name' => 'test-image.png',
        'file_size' => 1024,
        'mime_type' => 'image/png',
    ]);

    $response->assertOk();

    $data = $response->json();

    expect($data['path'])->toStartWith('landing-page-images/');
    expect($data['upload_url'])->toBeString();
    expect($data['public_url'])->toStartWith('https://cdn.example.com/landing-page-images/');
    expect($data['public_url'])->toContain('.png');
});

it('rejects non-image mime types for landing page images', function () {
    $response = $this->postJson(route('landing-pages.images.presign'), [
        'file_name' => 'document.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
    ]);

    $response->assertStatus(422);
});

it('rejects files larger than 4MB for landing page images', function () {
    $response = $this->postJson(route('landing-pages.images.presign'), [
        'file_name' => 'large-image.png',
        'file_size' => 5000000, // 5MB
        'mime_type' => 'image/png',
    ]);

    $response->assertStatus(422);
});

it('requires authentication for landing page image upload', function () {
    $this->actingAs(null);

    $response = $this->postJson(route('landing-pages.images.presign'), [
        'file_name' => 'test-image.png',
        'file_size' => 1024,
        'mime_type' => 'image/png',
    ]);

    $response->assertStatus(401);
});

it('generates unique paths for each landing page image upload', function () {
    config()->set('filesystems.disks.s3.url', 'https://cdn.example.com');
    Storage::fake('s3');

    $response1 = $this->postJson(route('landing-pages.images.presign'), [
        'file_name' => 'test-image.png',
        'file_size' => 1024,
        'mime_type' => 'image/png',
    ]);

    $response2 = $this->postJson(route('landing-pages.images.presign'), [
        'file_name' => 'test-image.png',
        'file_size' => 1024,
        'mime_type' => 'image/png',
    ]);

    $path1 = $response1->json('path');
    $path2 = $response2->json('path');

    expect($path1)->not->toBe($path2);
});
