<?php

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

it('requires authentication for contacts page', function () {
    $this->get(route('contacts.index'))
        ->assertRedirect(route('login'));
});

it('allows creating a contact group from livewire page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::contacts.index')
        ->set('groupName', 'Marketing Group')
        ->call('createGroup')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('contact_groups', [
        'name' => 'Marketing Group',
    ]);
});

it('opens modals from actions on the contacts page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::contacts.index')
        ->assertSet('showCreateGroupModal', false)
        ->assertSet('showImportModal', false)
        ->call('openCreateGroupModal')
        ->assertSet('showCreateGroupModal', true)
        ->call('openImportModal')
        ->assertSet('showImportModal', true);
});

it('imports contacts from csv and combines selected groups with csv groups column', function () {
    $this->actingAs(User::factory()->create());

    $uiGroup = ContactGroup::factory()->create();
    $csvGroup = ContactGroup::factory()->create();

    $csv = "email,firstName,lastName,company,isInvalid,groups\n";
    $csv .= "jane@example.com,Jane,Doe,Acme,false,{$csvGroup->id}\n";

    $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    Livewire::test('pages::contacts.index')
        ->set('selectedGroupIds', [$uiGroup->id])
        ->set('csvFile', $file)
        ->call('importContacts')
        ->assertHasNoErrors();

    $contact = Contact::query()->where('email', 'jane@example.com')->first();

    expect($contact)->not->toBeNull()
        ->and($contact->first_name)->toBe('Jane')
        ->and($contact->last_name)->toBe('Doe')
        ->and($contact->company)->toBe('Acme')
        ->and($contact->is_invalid)->toBeFalse();

    expect($contact->groups()->pluck('contact_groups.id')->all())
        ->toContain($uiGroup->id)
        ->toContain($csvGroup->id);
});

it('uses fullName when first and last names are missing in csv', function () {
    $this->actingAs(User::factory()->create());

    $csv = "email,fullName\n";
    $csv .= "sam@example.com,Sam Carter\n";

    $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    Livewire::test('pages::contacts.index')
        ->set('csvFile', $file)
        ->call('importContacts')
        ->assertHasNoErrors();

    $contact = Contact::query()->where('email', 'sam@example.com')->firstOrFail();

    expect($contact->first_name)->toBe('Sam')
        ->and($contact->last_name)->toBe('Carter')
        ->and($contact->full_name)->toBe('Sam Carter');
});

it('downloads contacts and groups csv from contacts page', function () {
    $this->actingAs(User::factory()->create());

    Contact::factory()->create();
    ContactGroup::factory()->create();

    Livewire::test('pages::contacts.index')
        ->call('exportContactsCsv')
        ->assertFileDownloaded();

    Livewire::test('pages::contacts.index')
        ->call('exportGroupsCsv')
        ->assertFileDownloaded();
});

it('downloads filtered group contacts as csv', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $matchingContact = Contact::factory()->create(['email' => 'match@example.com']);
    $otherContact = Contact::factory()->create(['email' => 'other@example.com']);

    $group->contacts()->attach([$matchingContact->id, $otherContact->id]);

    Livewire::test('pages::contacts.group-detail', ['group' => $group])
        ->set('search', 'match@example.com')
        ->call('exportCsv')
        ->assertFileDownloaded();
});

it('shows simplified contact page actions with a more dropdown', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('contacts.index'))
        ->assertSuccessful()
        ->assertSee('Add Contact')
        ->assertSee('Import CSV')
        ->assertSee('More')
        ->assertSee('Create Group')
        ->assertSee('Export Contacts')
        ->assertSee('Export Groups');
});
