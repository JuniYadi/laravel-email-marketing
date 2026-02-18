<?php

namespace App\Livewire\Templates;

use App\Models\EmailTemplate;
use Livewire\Component;

class AttachmentUpload extends Component
{
    public ?int $templateId = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $attachments = [];

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

    public function updatedAttachments(): void
    {
        $this->updateCalculatedProperties();
        $this->dispatch('attachmentsUpdated', attachments: $this->attachments);
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    public function appendUploadedAttachments(array $attachments): void
    {
        $currentTotal = collect($this->attachments)->sum('size');
        $incomingTotal = collect($attachments)->sum('size');

        if (($currentTotal + $incomingTotal) > EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES) {
            $this->addError('attachments', __('Adding these files would exceed the 40MB total limit.'));

            return;
        }

        $this->attachments = array_values(array_merge($this->attachments, $attachments));

        $this->updateCalculatedProperties();
        $this->dispatch('attachmentsUpdated', attachments: $this->attachments);
    }

    public function removeUploadedAttachment(string $attachmentId): void
    {
        $index = collect($this->attachments)
            ->search(fn (array $attachment): bool => (string) ($attachment['id'] ?? '') === $attachmentId);

        if ($index === false) {
            return;
        }

        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);

        $this->updateCalculatedProperties();
        $this->dispatch('attachmentsUpdated', attachments: $this->attachments);
    }

    protected function updateCalculatedProperties(): void
    {
        $this->totalSize = collect($this->attachments)->sum('size');

        if ($this->totalSize === 0) {
            $this->totalSizeFormatted = '0 B';
        } else {
            $units = ['B', 'KB', 'MB', 'GB'];
            $unitIndex = 0;
            $size = (float) $this->totalSize;

            while ($size >= 1024 && $unitIndex < count($units) - 1) {
                $size /= 1024;
                $unitIndex++;
            }

            $this->totalSizeFormatted = round($size, 2).' '.$units[$unitIndex];
        }

        $this->isOverLimit = $this->totalSize > EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;
        $this->remainingSize = max(0, EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES - $this->totalSize);

        $maxSize = EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;
        $this->progressPercentage = $maxSize === 0
            ? 0.0
            : min(100, ($this->totalSize / $maxSize) * 100);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.templates.attachment-upload');
    }
}
