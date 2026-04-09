<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteGalleryAssetRequest;
use App\Http\Requests\FinalizeGalleryAssetsRequest;
use App\Http\Requests\PresignGalleryAssetsRequest;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class GalleryAssetController extends Controller
{
    public function presign(PresignGalleryAssetsRequest $request): JsonResponse
    {
        $disk = 's3';

        $uploads = collect($request->validated('files'))
            ->map(function (array $file) use ($disk): array {
                $path = $this->buildStoragePath((string) $file['name']);

                try {
                    ['url' => $url, 'headers' => $headers] = Storage::disk($disk)->temporaryUploadUrl(
                        $path,
                        now()->addMinutes(10)
                    );
                } catch (Throwable) {
                    throw ValidationException::withMessages([
                        'files' => __('Unable to prepare upload URL. Check storage driver configuration.'),
                    ]);
                }

                return [
                    'name' => (string) $file['name'],
                    'size' => (int) $file['size'],
                    'mime_type' => (string) $file['mime_type'],
                    'path' => $path,
                    'disk' => $disk,
                    'upload_url' => $url,
                    'upload_headers' => $headers,
                    'method' => 'PUT',
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'uploads' => $uploads,
        ]);
    }

    public function finalize(FinalizeGalleryAssetsRequest $request): JsonResponse
    {
        $assets = [];

        foreach ($request->validated('uploads') as $upload) {
            $disk = (string) $upload['disk'];
            $path = (string) $upload['path'];
            $mimeType = (string) $upload['mime_type'];

            if (! $this->isSafeGalleryPath($path)) {
                throw ValidationException::withMessages([
                    'uploads' => __('Invalid gallery asset path.'),
                ]);
            }

            if (! Storage::disk($disk)->exists($path)) {
                throw ValidationException::withMessages([
                    'uploads' => __('One or more uploaded files could not be found. Please upload again.'),
                ]);
            }

            $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
            $size = (int) Storage::disk($disk)->size($path);

            $asset = MediaAsset::query()->create([
                'user_id' => $request->user()?->id,
                'original_name' => (string) $upload['name'],
                'storage_disk' => $disk,
                'storage_path' => $path,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size_bytes' => $size,
                'public_url' => $this->getPublicUrl($disk, $path),
                'kind' => $this->resolveKind($mimeType),
            ]);

            $assets[] = [
                'id' => $asset->id,
                'external_id' => $asset->external_id,
                'storage_path' => $asset->storage_path,
                'public_url' => $asset->public_url,
                'kind' => $asset->kind,
            ];
        }

        return response()->json([
            'assets' => $assets,
        ]);
    }

    public function trash(DeleteGalleryAssetRequest $request, MediaAsset $asset): JsonResponse
    {
        $asset->delete();

        return response()->json([
            'trashed' => true,
        ]);
    }

    public function restore(int $asset): JsonResponse
    {
        $mediaAsset = MediaAsset::withTrashed()->findOrFail($asset);
        $mediaAsset->restore();

        return response()->json([
            'restored' => true,
        ]);
    }

    protected function buildStoragePath(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $safeFilename = Str::slug($filename, '-');
        $safeFilename = $safeFilename !== '' ? $safeFilename : 'file';

        return sprintf(
            'gallery-assets/%s-%s.%s',
            Str::uuid7(),
            $safeFilename,
            $extension
        );
    }

    protected function isSafeGalleryPath(string $path): bool
    {
        return str_starts_with($path, 'gallery-assets/')
            && ! str_contains($path, '..');
    }

    protected function getPublicUrl(string $disk, string $path): string
    {
        $configuredBaseUrl = trim((string) config('filesystems.disks.'.$disk.'.url'), '/');

        if ($configuredBaseUrl !== '') {
            return $configuredBaseUrl.'/'.$path;
        }

        return Storage::disk($disk)->url($path);
    }

    protected function resolveKind(string $mimeType): string
    {
        return str_starts_with($mimeType, 'image/')
            ? MediaAsset::KIND_IMAGE
            : MediaAsset::KIND_PDF;
    }
}
