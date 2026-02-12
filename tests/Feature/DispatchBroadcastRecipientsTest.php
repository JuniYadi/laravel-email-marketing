<?php

use App\Jobs\SendBroadcastRecipientMail;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('only dispatches mail for subscribed contacts', function () {
    Bus::fake([SendBroadcastRecipientMail::class]);

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $subscribed = Contact::factory()->create(['unsubscribed_at' => null]);
    $unsubscribed = Contact::factory()->create(['unsubscribed_at' => now()]);

    $group->contacts()->attach([$subscribed->id, $unsubscribed->id]);

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_RUNNING,
        'messages_per_minute' => 100,
        'snapshot_subject' => 'Test',
        'snapshot_html_content' => '<p>Test</p>',
        'snapshot_template_version' => 1,
        'from_email' => 'test@example.com',
    ]);

    $this->artisan('broadcasts:dispatch')
        ->assertExitCode(0);

    $broadcast->refresh();

    expect($broadcast->recipients()->count())->toBe(1);
    expect($broadcast->recipients()->first()->contact_id)->toBe($subscribed->id);
});
