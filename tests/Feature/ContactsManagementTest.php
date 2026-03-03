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

it('imports normalized custom fields from csv', function () {
    $this->actingAs(User::factory()->create());

    $csv = "email,firstName,lastName,Voucher Code,Loyalty-Tier\n";
    $csv .= "sam@example.com,Sam,Carter,ABC123,Gold\n";

    $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    Livewire::test('pages::contacts.index')
        ->set('csvFile', $file)
        ->call('importContacts')
        ->assertHasNoErrors();

    $contact = Contact::query()->where('email', 'sam@example.com')->firstOrFail();

    expect($contact->custom_fields)->toBe([
        'voucher_code' => 'ABC123',
        'loyalty_tier' => 'Gold',
    ]);
});

it('keeps existing custom field values when re-import csv has blank value', function () {
    $this->actingAs(User::factory()->create());

    $initialCsv = "email,firstName,lastName,Voucher Code\n";
    $initialCsv .= "jane@example.com,Jane,Doe,ABC123\n";

    Livewire::test('pages::contacts.index')
        ->set('csvFile', UploadedFile::fake()->createWithContent('contacts.csv', $initialCsv))
        ->call('importContacts')
        ->assertHasNoErrors();

    $secondCsv = "email,firstName,lastName,Voucher Code,Loyalty Tier\n";
    $secondCsv .= "jane@example.com,Jane,Doe,,Platinum\n";

    Livewire::test('pages::contacts.index')
        ->set('csvFile', UploadedFile::fake()->createWithContent('contacts.csv', $secondCsv))
        ->call('importContacts')
        ->assertHasNoErrors();

    $contact = Contact::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($contact->custom_fields)->toBe([
        'voucher_code' => 'ABC123',
        'loyalty_tier' => 'Platinum',
    ]);
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

it('includes discovered custom fields in exported contacts csv', function () {
    $this->actingAs(User::factory()->create());

    Contact::factory()->create([
        'email' => 'a@example.com',
        'custom_fields' => [
            'voucher_code' => 'AAA111',
        ],
    ]);

    Contact::factory()->create([
        'email' => 'b@example.com',
        'custom_fields' => [
            'loyalty_tier' => 'Gold',
        ],
    ]);

    $testable = Livewire::test('pages::contacts.index')
        ->call('exportContactsCsv')
        ->assertFileDownloaded();

    $response = $testable->instance()->exportContactsCsv();

    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    $lines = array_values(array_filter(explode("\n", trim($csv))));
    $header = str_getcsv($lines[0]);

    expect($header)->toContain('voucher_code')
        ->and($header)->toContain('loyalty_tier');
});

it('downloads import sample csv with custom field examples', function () {
    $this->actingAs(User::factory()->create());

    $testable = Livewire::test('pages::contacts.index')
        ->call('downloadImportSampleCsv')
        ->assertFileDownloaded();

    $response = $testable->instance()->downloadImportSampleCsv();

    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    $lines = array_values(array_filter(explode("\n", trim($csv))));
    $header = str_getcsv($lines[0]);

    expect($header)->toBe([
        'email',
        'firstName',
        'lastName',
        'company',
        'isInvalid',
        'groups',
        'voucher_code',
        'loyalty_tier',
    ]);

    $firstDataRow = str_getcsv($lines[1]);

    expect($firstDataRow[0])->toBe('alice@example.com')
        ->and($firstDataRow[6])->toBe('VC-1001')
        ->and($firstDataRow[7])->toBe('Gold');
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
