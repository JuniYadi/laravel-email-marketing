<?php

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\EmailTemplate;
use App\Models\User;
use Livewire\Livewire;

it('requires authentication for broadcasts page', function () {
    $this->get(route('broadcasts.index'))
        ->assertRedirect(route('login'));
});

it('creates a broadcast from livewire page', function () {
    config()->set('broadcast.allowed_domains', ['test.com', 'marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create([
        'subject' => 'Welcome {{ first_name }}',
        'html_content' => '<h1>Hello {{ first_name }}</h1>',
    ]);

    Livewire::test('pages::broadcasts.index')
        ->set('broadcastName', 'Spring Campaign')
        ->set('broadcastGroupId', (string) $group->id)
        ->set('broadcastTemplateId', (string) $template->id)
        ->set('broadcastReplyTo', 'reply@example.com')
        ->set('broadcastFromName', 'Marketing Team')
        ->set('broadcastFromPrefix', 'juniyadi')
        ->set('broadcastFromDomain', 'marketing.test.com')
        ->set('broadcastMessagesPerMinute', 2)
        ->set('broadcastStartsAt', '2026-02-12T10:30')
        ->call('createBroadcast')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('broadcasts', [
        'name' => 'Spring Campaign',
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'reply_to' => 'reply@example.com',
        'from_name' => 'Marketing Team',
        'from_prefix' => 'juniyadi',
        'from_domain' => 'marketing.test.com',
        'messages_per_minute' => 2,
        'status' => 'scheduled',
    ]);
});

it('shows recipient list modal and allows requeueing failed recipients', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();
    $contact = Contact::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_COMPLETED,
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contact->id,
        'email' => 'failed@example.com',
        'status' => BroadcastRecipient::STATUS_FAILED,
        'last_error' => 'Mail transport failed',
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openRecipientsModal', $broadcast->id)
        ->assertSet('showRecipientsModal', true)
        ->assertSee('failed@example.com')
        ->call('requeueBroadcast', $broadcast->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('broadcasts', [
        'id' => $broadcast->id,
        'status' => Broadcast::STATUS_RUNNING,
    ]);

    $this->assertDatabaseHas('broadcast_recipients', [
        'broadcast_id' => $broadcast->id,
        'email' => 'failed@example.com',
        'status' => BroadcastRecipient::STATUS_PENDING,
    ]);
});

it('renders history link for each broadcast row', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->assertSee(route('broadcasts.history', ['broadcast_id' => $broadcast->id]));
});
