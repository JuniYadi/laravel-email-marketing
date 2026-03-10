<?php

namespace App\Http\Controllers\LandingPages;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LandingPageImageController extends Controller
{
    public function presign(Request $request): JsonResponse
    {
        $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'max:4096000'],
            'mime_type' => ['required', 'string', 'regex:/^image\/(jpeg|png|gif|webp|svg\+xml)$/'],
        ]);

        $disk = 's3';

        if (! $this->isS3Disk($disk)) {
            throw ValidationException::withMessages([
                'file_name' => __('Image uploads require an s3 filesystem disk.'),
            ]);
        }

        $path = $this->buildImagePath($request->input('file_name'));

        try {
            ['url' => $url, 'headers' => $headers] = Storage::disk($disk)->temporaryUploadUrl(
                $path,
                now()->addMinutes(10)
            );
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file_name' => __('Unable to prepare upload URL. Check storage driver configuration.'),
            ]);
        }

        $publicUrl = $this->getPublicUrl($disk, $path);

        return response()->json([
            'path' => $path,
            'disk' => $disk,
            'upload_url' => $url,
            'upload_headers' => $headers,
            'public_url' => $publicUrl,
            'method' => 'PUT',
        ]);
    }

    protected function isS3Disk(string $disk): bool
    {
        return (string) config('filesystems.disks.'.$disk.'.driver') === 's3';
    }

    protected function buildImagePath(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $safeFilename = Str::slug($filename, '-');
        $safeFilename = $safeFilename !== '' ? $safeFilename : 'image';

        return sprintf(
            'landing-page-images/%s-%s.%s',
            Str::ulid(),
            $safeFilename,
            $extension
        );
    }

    protected function getPublicUrl(string $disk, string $path): string
    {
        $configuredBaseUrl = trim((string) config('filesystems.disks.'.$disk.'.url'), '/');

        if ($configuredBaseUrl !== '') {
            return $configuredBaseUrl.'/'.$path;
        }

        return Storage::disk($disk)->url($path);
    }
}
