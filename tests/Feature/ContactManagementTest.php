<?php

use App\Models\Contact;
use App\Models\ContactGroup;

it('builds full name from first and last name', function () {
    $contact = Contact::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    expect($contact->full_name)->toBe('Jane Doe');
});

it('allows a contact to belong to multiple groups', function () {
    $contact = Contact::factory()->create();
    $groups = ContactGroup::factory()->count(2)->create();

    $contact->groups()->attach($groups->pluck('id'));

    expect($contact->groups()->count())->toBe(2)
        ->and($groups->first()->contacts()->count())->toBe(1);
});

it('stores group broadcast defaults', function () {
    $group = ContactGroup::factory()->create();

    expect($group->start_broadcast)->toBeFalse()
        ->and($group->message_per_minutes)->toBe(1);
});

it('stores optional company and invalid status for contacts', function () {
    $contact = Contact::factory()->create([
        'company' => null,
        'is_invalid' => false,
    ]);

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'company' => null,
        'is_invalid' => 0,
    ]);
});
