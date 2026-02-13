<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class BroadcastRecipientMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $attachmentData = [];

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $subjectLine,
        public string $htmlContent,
        public string $fromName,
        public string $fromEmail,
        public string $replyToAddress,
        /** @var array<string, string> */
        public array $messageMetadata = [],
        public string $unsubscribeUrl = '',
        array $attachments = [],
    ) {
        $this->attachmentData = $attachments;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            replyTo: [new Address($this->replyToAddress)],
            subject: $this->subjectLine,
            tags: ['broadcast'],
            metadata: $this->messageMetadata,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlContent,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->attachmentData as $attachment) {
            if (! isset($attachment['path']) || ! isset($attachment['disk'])) {
                continue;
            }

            $attachmentPath = Storage::disk($attachment['disk'])->path($attachment['path']);

            if (! file_exists($attachmentPath)) {
                continue;
            }

            $attachments[] = \Illuminate\Mail\Attachment::fromPath($attachmentPath)
                ->as($attachment['name'] ?? basename($attachment['path']))
                ->withMime($attachment['mime_type'] ?? 'application/octet-stream');
        }

        return $attachments;
    }

    /**
     * Get the message headers.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        if ($this->unsubscribeUrl === '') {
            return [];
        }

        return [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
    }
}
