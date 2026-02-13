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

    public function mount(?int $templateId = null): void
    {
        $this->templateId = $templateId;

        if ($templateId !== null) {
            $template = EmailTemplate::query()->find($templateId);
            if ($template !== null) {
                $this->attachments = $template->attachments ?? [];
            }
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
            'newAttachments.*.mimetypes' => 'The file must be a PDF, Word document, Excel spreadsheet, or PowerPoint presentation.',
            'newAttachments.*.mimes' => 'Invalid file extension. Allowed: pdf, docx, doc, xlsx, xls, pptx, ppt',
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
        $futureTotal = $this->getTotalSizeProperty() + $file->getSize();
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
            $futureTotal = $this->getTotalSizeProperty() + $file->getSize();
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

        $this->dispatch('attachmentsUpdated', attachments: $this->attachments);
    }

    public function getTotalSizeProperty(): int
    {
        return collect($this->attachments)->sum('size');
    }

    public function getTotalSizeFormattedProperty(): string
    {
        $size = $this->getTotalSizeProperty();
        if ($size === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2).' '.$units[$unitIndex];
    }

    public function getIsOverLimitProperty(): bool
    {
        return $this->getTotalSizeProperty() > EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;
    }

    public function getRemainingSizeProperty(): int
    {
        return max(0, EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES - $this->getTotalSizeProperty());
    }

    public function getProgressPercentageProperty(): float
    {
        $maxSize = EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;
        if ($maxSize === 0) {
            return 0.0;
        }

        return min(100, ($this->getTotalSizeProperty() / $maxSize) * 100);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.templates.attachment-upload');
    }
}
