<?php

namespace App\Livewire\Templates;

use App\Models\EmailTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class AttachmentUpload extends Component
{
    use WithFileUploads;

    public ?int $templateId = null;

    public array $attachments = [];

    /**
     * @var array<int, UploadedFile>
     */
    public array $newAttachments = [];

    public int $totalSize = 0;

    public string $totalSizeFormatted = '0 B';

    public bool $isOverLimit = false;

    public float $progressPercentage = 0.0;

    public int $remainingSize = 0;

    public function mount(?int $templateId = null): void
    {
        $this->templateId = $templateId;

        if ($templateId !== null) {
            $template = EmailTemplate::query()->find($templateId);
            if ($template !== null) {
                $this->attachments = $template->attachments ?? [];
            }
        }

        $this->updateCalculatedProperties();
    }

    /**
     * Livewire lifecycle hook - called when properties are updated.
     */
    public function updatedAttachments(): void
    {
        $this->updateCalculatedProperties();
    }

    /**
     * Update all calculated properties based on current attachments.
     */
    protected function updateCalculatedProperties(): void
    {
        $this->totalSize = collect($this->attachments)->sum('size');

        // Update total size formatted
        if ($this->totalSize === 0) {
            $this->totalSizeFormatted = '0 B';
        } else {
            $units = ['B', 'KB', 'MB', 'GB'];
            $unitIndex = 0;
            $size = $this->totalSize;

            while ($size >= 1024 && $unitIndex < count($units) - 1) {
                $size /= 1024;
                $unitIndex++;
            }

            $this->totalSizeFormatted = round($size, 2).' '.$units[$unitIndex];
        }

        // Update is over limit
        $this->isOverLimit = $this->totalSize > EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;

        // Update remaining size
        $this->remainingSize = max(0, EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES - $this->totalSize);

        // Update progress percentage
        $maxSize = EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;
        if ($maxSize === 0) {
            $this->progressPercentage = 0.0;
        } else {
            $this->progressPercentage = min(100, ($this->totalSize / $maxSize) * 100);
        }
    }

    public function updatedNewAttachments(): void
    {
        $this->validate([
            'newAttachments.*' => [
                'file',
                'max:40960', // 40MB per file
                'mimetypes:'.implode(',', EmailTemplate::ALLOWED_ATTACHMENT_MIME_TYPES),
                'mimes:'.implode(',', EmailTemplate::ALLOWED_ATTACHMENT_EXTENSIONS),
            ],
        ], [
            'newAttachments.*.file' => 'One or more files failed to upload. Please try again.',
            'newAttachments.*.max' => 'One or more files exceed the 40MB maximum file size.',
            'newAttachments.*.mimetypes' => 'One or more files are not a valid type. Only PDF, Word, Excel, and PowerPoint files are allowed.',
            'newAttachments.*.mimes' => 'One or more files have an invalid extension. Allowed: pdf, doc, docx, xls, xlsx, ppt, pptx',
        ]);
    }

    public function addAttachment(int $index): void
    {
        if (! isset($this->newAttachments[$index])) {
            return;
        }

        $file = $this->newAttachments[$index];

        if (! $file instanceof UploadedFile) {
            return;
        }

        // Check if adding this file would exceed the 40MB total limit
        $currentTotal = collect($this->attachments)->sum('size');
        $futureTotal = $currentTotal + $file->getSize();
        if ($futureTotal > EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES) {
            $this->addError('newAttachments.'.$index, 'Adding this file would exceed the 40MB total limit.');

            return;
        }

        // Store file on default disk
        $disk = config('filesystems.default');
        $path = Storage::disk($disk)->putFile('template-attachments', $file);

        $this->attachments[] = [
            'id' => (string) str()->ulid(),
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => $disk,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_at' => now()->toIso8601String(),
        ];

        unset($this->newAttachments[$index]);
        $this->newAttachments = array_values($this->newAttachments);

        $this->updateCalculatedProperties();
        $this->dispatch('attachmentsUpdated', attachments: $this->attachments);
    }

    public function removeAttachment(string $attachmentId): void
    {
        $index = collect($this->attachments)->search(fn (array $a): bool => $a['id'] === $attachmentId);

        if ($index === false) {
            return;
        }

        $attachment = $this->attachments[$index];

        // Delete file from storage
        if (isset($attachment['path']) && isset($attachment['disk'])) {
            Storage::disk($attachment['disk'])->delete($attachment['path']);
        }

        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);

        $this->updateCalculatedProperties();
        $this->dispatch('attachmentsUpdated', attachments: $this->attachments);
    }

    public function removeNewAttachment(int $index): void
    {
        if (! isset($this->newAttachments[$index])) {
            return;
        }

        unset($this->newAttachments[$index]);
        $this->newAttachments = array_values($this->newAttachments);
    }

    public function clearNewAttachments(): void
    {
        $this->newAttachments = [];
        $this->resetErrorBag();
    }

    public function addAllAttachments(): void
    {
        if (empty($this->newAttachments)) {
            return;
        }

        $addedCount = 0;
        $skippedCount = 0;
        $skippedFiles = [];

        foreach ($this->newAttachments as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            // Check if adding this file would exceed the 40MB total limit
            $currentTotal = collect($this->attachments)->sum('size');
            $futureTotal = $currentTotal + $file->getSize();
            if ($futureTotal > EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES) {
                $skippedCount++;
                $skippedFiles[] = $file->getClientOriginalName();

                continue;
            }

            // Store file on default disk
            $disk = config('filesystems.default');
            $path = Storage::disk($disk)->putFile('template-attachments', $file);

            $this->attachments[] = [
                'id' => (string) str()->ulid(),
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'disk' => $disk,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_at' => now()->toIso8601String(),
            ];

            $addedCount++;
        }

        // Clear the pending uploads
        $this->newAttachments = [];

        // Show warning if files were skipped
        if ($skippedCount > 0) {
            $message = sprintf(
                'Warning: %d file(s) were skipped as they would exceed the 40MB limit: %s',
                $skippedCount,
                implode(', ', $skippedFiles)
            );
            $this->addError('newAttachments', $message);
        }

        $this->updateCalculatedProperties();
        $this->dispatch('attachmentsUpdated', attachments: $this->attachments);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.templates.attachment-upload');
    }
}
