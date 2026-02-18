<?php

namespace App\Http\Controllers\Templates;

use App\Http\Controllers\Controller;
use App\Http\Requests\CleanupUnsavedTemplateAttachmentsRequest;
use App\Http\Requests\DeleteTemplateAttachmentRequest;
use App\Http\Requests\FinalizeTemplateAttachmentsRequest;
use App\Http\Requests\PresignTemplateAttachmentsRequest;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TemplateAttachmentController extends Controller
{
    public function presign(PresignTemplateAttachmentsRequest $request): JsonResponse
    {
        $disk = $this->defaultDisk();

        $this->ensureTemporaryUploadUrlSupported($disk);

        $uploads = collect($request->validated('files'))
            ->map(function (array $file) use ($disk): array {
                $path = $this->buildAttachmentPath((string) $file['name']);

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

    public function finalize(FinalizeTemplateAttachmentsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $uploadedAttachments = [];

        foreach ($validated['uploads'] as $upload) {
            $disk = (string) ($upload['disk'] ?? $this->defaultDisk());
            $path = (string) $upload['path'];

            if (! $this->isSafeAttachmentPath($path)) {
                throw ValidationException::withMessages([
                    'uploads' => __('Invalid attachment path.'),
                ]);
            }

            if (! Storage::disk($disk)->exists($path)) {
                throw ValidationException::withMessages([
                    'uploads' => __('One or more uploaded files could not be found. Please upload again.'),
                ]);
            }

            $storedSize = (int) Storage::disk($disk)->size($path);

            if ($storedSize > EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES) {
                throw ValidationException::withMessages([
                    'uploads' => __('One or more files exceed the 40MB maximum file size.'),
                ]);
            }

            $uploadedAttachments[] = [
                'id' => (string) Str::ulid(),
                'name' => (string) $upload['name'],
                'path' => $path,
                'disk' => $disk,
                'size' => $storedSize,
                'mime_type' => (string) $upload['mime_type'],
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        $batchSize = collect($uploadedAttachments)->sum('size');

        if ($batchSize > EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'uploads' => __('Adding these files would exceed the 40MB total limit.'),
            ]);
        }

        return response()->json([
            'attachments' => $uploadedAttachments,
        ]);
    }

    public function delete(DeleteTemplateAttachmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $path = (string) $validated['path'];
        $disk = (string) $validated['disk'];

        if ($this->isSafeAttachmentPath($path)) {
            Storage::disk($disk)->delete($path);
        }

        return response()->json([
            'deleted' => true,
        ]);
    }

    public function cleanupUnsaved(CleanupUnsavedTemplateAttachmentsRequest $request): JsonResponse
    {
        foreach ($request->validated('attachments') as $attachment) {
            $path = (string) Arr::get($attachment, 'path', '');
            $disk = (string) Arr::get($attachment, 'disk', $this->defaultDisk());

            if (! $this->isSafeAttachmentPath($path)) {
                continue;
            }

            Storage::disk($disk)->delete($path);
        }

        return response()->json([
            'cleaned' => true,
        ]);
    }

    protected function defaultDisk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    protected function ensureTemporaryUploadUrlSupported(string $disk): void
    {
        $driver = (string) config('filesystems.disks.'.$disk.'.driver');

        if ($driver !== 's3') {
            throw ValidationException::withMessages([
                'files' => __('Presigned attachment uploads require an s3 filesystem disk.'),
            ]);
        }
    }

    protected function buildAttachmentPath(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $safeFilename = Str::slug($filename, '-');
        $safeFilename = $safeFilename !== '' ? $safeFilename : 'file';

        return sprintf(
            'template-attachments/%s-%s.%s',
            Str::ulid(),
            $safeFilename,
            $extension
        );
    }

    protected function isSafeAttachmentPath(string $path): bool
    {
        return str_starts_with($path, 'template-attachments/')
            && ! str_contains($path, '..');
    }
}
