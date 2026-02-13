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

// Dropdown visibility tests
it('shows start button for scheduled broadcast', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_SCHEDULED,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->assertSee('Start');
});

it('shows pause button for running broadcast', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_RUNNING,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->assertSee('Pause');
});

it('shows resume button for paused broadcast', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_PAUSED,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->assertSee('Resume');
});

it('hides edit option for running broadcast', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_RUNNING,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openEditBroadcastModal', $broadcast->id)
        ->assertSet('showEditBroadcastModal', false);
});

it('hides edit option for completed broadcast', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_COMPLETED,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openEditBroadcastModal', $broadcast->id)
        ->assertSet('showEditBroadcastModal', false);
});

// Edit functionality tests
it('can open edit modal for scheduled broadcast', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'name' => 'Test Campaign',
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_SCHEDULED,
        'reply_to' => 'reply@test.com',
        'from_name' => 'Test Sender',
        'from_prefix' => 'test',
        'from_domain' => 'marketing.test.com',
        'messages_per_minute' => 5,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openEditBroadcastModal', $broadcast->id)
        ->assertSet('showEditBroadcastModal', true)
        ->assertSet('editBroadcastName', 'Test Campaign')
        ->assertSet('editBroadcastGroupId', (string) $group->id)
        ->assertSet('editBroadcastTemplateId', (string) $template->id)
        ->assertSet('editBroadcastReplyTo', 'reply@test.com')
        ->assertSet('editBroadcastFromName', 'Test Sender')
        ->assertSet('editBroadcastFromPrefix', 'test')
        ->assertSet('editBroadcastFromDomain', 'marketing.test.com')
        ->assertSet('editBroadcastMessagesPerMinute', 5);
});

it('can open edit modal for draft broadcast', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_DRAFT,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openEditBroadcastModal', $broadcast->id)
        ->assertSet('showEditBroadcastModal', true);
});

it('cannot edit running broadcast', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_RUNNING,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openEditBroadcastModal', $broadcast->id)
        ->assertSet('showEditBroadcastModal', false);
});

it('cannot edit completed broadcast', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_COMPLETED,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openEditBroadcastModal', $broadcast->id)
        ->assertSet('showEditBroadcastModal', false);
});

it('can update broadcast name', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'name' => 'Original Name',
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_SCHEDULED,
        'reply_to' => 'reply@test.com',
        'from_name' => 'Sender',
        'from_prefix' => 'test',
        'from_domain' => 'marketing.test.com',
        'messages_per_minute' => 1,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openEditBroadcastModal', $broadcast->id)
        ->set('editBroadcastName', 'Updated Name')
        ->call('updateBroadcast')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('broadcasts', [
        'id' => $broadcast->id,
        'name' => 'Updated Name',
    ]);
});

it('can update broadcast schedule', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_SCHEDULED,
        'reply_to' => 'reply@test.com',
        'from_name' => 'Sender',
        'from_prefix' => 'test',
        'from_domain' => 'marketing.test.com',
        'messages_per_minute' => 1,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openEditBroadcastModal', $broadcast->id)
        ->set('editBroadcastStartsAt', '2026-03-15T14:00')
        ->call('updateBroadcast')
        ->assertHasNoErrors();

    $broadcast->refresh();
    expect($broadcast->starts_at->format('Y-m-d H:i'))->toBe('2026-03-15 14:00');
});

it('validates required fields on edit', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_SCHEDULED,
        'reply_to' => 'reply@test.com',
        'from_name' => 'Sender',
        'from_prefix' => 'test',
        'from_domain' => 'marketing.test.com',
        'messages_per_minute' => 1,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openEditBroadcastModal', $broadcast->id)
        ->set('editBroadcastName', '')
        ->set('editBroadcastReplyTo', 'invalid-email')
        ->call('updateBroadcast')
        ->assertHasErrors(['editBroadcastName', 'editBroadcastReplyTo']);
});

it('validates prefix format on edit', function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);

    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_SCHEDULED,
        'reply_to' => 'reply@test.com',
        'from_name' => 'Sender',
        'from_prefix' => 'test',
        'from_domain' => 'marketing.test.com',
        'messages_per_minute' => 1,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openEditBroadcastModal', $broadcast->id)
        ->set('editBroadcastFromPrefix', '!!!')
        ->call('updateBroadcast')
        ->assertHasErrors('editBroadcastFromPrefix');
});
