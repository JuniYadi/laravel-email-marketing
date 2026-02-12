<?php

use App\Mail\BroadcastRecipientMail;

it('builds broadcast recipient envelope without colliding with parent mailable properties', function () {
    $mailable = new BroadcastRecipientMail(
        subjectLine: 'Hello',
        htmlContent: '<p>Hi</p>',
        fromName: 'Marketing Team',
        fromEmail: 'sender-b1-abcdef@marketing.test.com',
        replyToAddress: 'reply@example.com',
        messageMetadata: [
            'broadcast_id' => '1',
            'broadcast_recipient_id' => '22',
        ],
    );

    $envelope = $mailable->envelope();

    expect($envelope->subject)->toBe('Hello')
        ->and($envelope->from->address)->toBe('sender-b1-abcdef@marketing.test.com')
        ->and($envelope->replyTo[0]->address)->toBe('reply@example.com')
        ->and($envelope->metadata)->toMatchArray([
            'broadcast_id' => '1',
            'broadcast_recipient_id' => '22',
        ]);
});
