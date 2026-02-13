<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TemplateTestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $subjectLine,
        public string $htmlContent,
    ) {}

    public function build(): static
    {
        return $this->subject($this->subjectLine)
            ->html($this->htmlContent);
    }
}
