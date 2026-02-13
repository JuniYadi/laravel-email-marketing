<?php

use App\Models\Broadcast;
use App\Models\ContactGroup;
use App\Models\EmailTemplate;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('broadcast.allowed_domains', ['marketing.test.com']);
});

it('guest cannot duplicate broadcast', function () {
    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
    ]);

    $this->get(route('broadcasts.index'))
        ->assertRedirect(route('login'));
});

it('user can duplicate broadcast with current template', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'name' => 'Original Campaign',
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_COMPLETED,
        'messages_per_minute' => 10,
        'reply_to' => 'reply@example.com',
        'from_name' => 'Marketing',
        'from_prefix' => 'marketing',
        'from_domain' => 'marketing.test.com',
        'starts_at' => now()->addDay(),
        'started_at' => now()->subHour(),
        'completed_at' => now(),
        'snapshot_subject' => 'Old Subject',
        'snapshot_html_content' => '<p>Old Content</p>',
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openDuplicateModal', $broadcast->id)
        ->assertSet('showDuplicateModal', true)
        ->assertSet('duplicateBroadcastId', $broadcast->id)
        ->assertSet('duplicateName', 'Original Campaign')
        ->set('duplicateSnapshotChoice', 'template')
        ->call('duplicateBroadcast')
        ->assertHasNoErrors();

    $duplicated = Broadcast::query()
        ->where('name', 'Original Campaign')
        ->where('id', '!=', $broadcast->id)
        ->first();

    expect($duplicated)->not->toBeNull()
        ->and($duplicated->status)->toBe(Broadcast::STATUS_DRAFT)
        ->and($duplicated->contact_group_id)->toBe($group->id)
        ->and($duplicated->email_template_id)->toBe($template->id)
        ->and($duplicated->messages_per_minute)->toBe(10)
        ->and($duplicated->reply_to)->toBe('reply@example.com')
        ->and($duplicated->from_name)->toBe('Marketing')
        ->and($duplicated->from_prefix)->toBe('marketing')
        ->and($duplicated->from_domain)->toBe('marketing.test.com')
        ->and($duplicated->starts_at)->toBeNull()
        ->and($duplicated->started_at)->toBeNull()
        ->and($duplicated->completed_at)->toBeNull()
        ->and($duplicated->snapshot_subject)->toBeNull()
        ->and($duplicated->snapshot_html_content)->toBeNull();
});

it('user can duplicate broadcast with original snapshots', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'name' => 'Campaign with Snapshots',
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_COMPLETED,
        'snapshot_subject' => 'Preserved Subject',
        'snapshot_html_content' => '<p>Preserved Content</p>',
        'snapshot_builder_schema' => ['blocks' => [['type' => 'text']]],
        'snapshot_template_version' => 5,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openDuplicateModal', $broadcast->id)
        ->set('duplicateSnapshotChoice', 'original')
        ->call('duplicateBroadcast')
        ->assertHasNoErrors();

    $duplicated = Broadcast::query()
        ->where('name', 'Campaign with Snapshots')
        ->where('id', '!=', $broadcast->id)
        ->first();

    expect($duplicated)->not->toBeNull()
        ->and($duplicated->snapshot_subject)->toBe('Preserved Subject')
        ->and($duplicated->snapshot_html_content)->toBe('<p>Preserved Content</p>')
        ->and($duplicated->snapshot_builder_schema)->toBe(['blocks' => [['type' => 'text']]])
        ->and($duplicated->snapshot_template_version)->toBe(5);
});

it('duplicate requires name', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openDuplicateModal', $broadcast->id)
        ->set('duplicateName', '')
        ->call('duplicateBroadcast')
        ->assertHasErrors(['duplicateName' => 'required']);
});

it('duplicate has unique id', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
    ]);

    $originalId = $broadcast->id;

    Livewire::test('pages::broadcasts.index')
        ->call('openDuplicateModal', $broadcast->id)
        ->call('duplicateBroadcast')
        ->assertHasNoErrors();

    $duplicated = Broadcast::query()
        ->where('id', '!=', $originalId)
        ->first();

    expect($duplicated)->not->toBeNull()
        ->and($duplicated->id)->not->toBe($originalId);
});

it('duplicate resets execution fields', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_COMPLETED,
        'starts_at' => now()->addDay(),
        'started_at' => now()->subHour(),
        'completed_at' => now(),
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openDuplicateModal', $broadcast->id)
        ->call('duplicateBroadcast')
        ->assertHasNoErrors();

    $duplicated = Broadcast::query()
        ->where('id', '!=', $broadcast->id)
        ->first();

    expect($duplicated->status)->toBe(Broadcast::STATUS_DRAFT)
        ->and($duplicated->starts_at)->toBeNull()
        ->and($duplicated->started_at)->toBeNull()
        ->and($duplicated->completed_at)->toBeNull();
});

it('duplicate copies config fields', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'messages_per_minute' => 50,
        'reply_to' => 'custom-reply@test.com',
        'from_name' => 'Custom Sender',
        'from_prefix' => 'custom-prefix',
        'from_domain' => 'marketing.test.com',
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openDuplicateModal', $broadcast->id)
        ->call('duplicateBroadcast')
        ->assertHasNoErrors();

    $duplicated = Broadcast::query()
        ->where('id', '!=', $broadcast->id)
        ->first();

    expect($duplicated->contact_group_id)->toBe($group->id)
        ->and($duplicated->email_template_id)->toBe($template->id)
        ->and($duplicated->messages_per_minute)->toBe(50)
        ->and($duplicated->reply_to)->toBe('custom-reply@test.com')
        ->and($duplicated->from_name)->toBe('Custom Sender')
        ->and($duplicated->from_prefix)->toBe('custom-prefix')
        ->and($duplicated->from_domain)->toBe('marketing.test.com');
});

it('duplicate name can be customized', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'name' => 'Original Name',
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openDuplicateModal', $broadcast->id)
        ->set('duplicateName', 'Custom Duplicate Name')
        ->call('duplicateBroadcast')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('broadcasts', [
        'name' => 'Custom Duplicate Name',
        'status' => Broadcast::STATUS_DRAFT,
    ]);
});

it('duplicate creates draft status', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_RUNNING,
    ]);

    Livewire::test('pages::broadcasts.index')
        ->call('openDuplicateModal', $broadcast->id)
        ->call('duplicateBroadcast')
        ->assertHasNoErrors();

    $duplicated = Broadcast::query()
        ->where('id', '!=', $broadcast->id)
        ->first();

    expect($duplicated->status)->toBe(Broadcast::STATUS_DRAFT);
});
