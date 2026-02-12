<?php

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('excludes unsubscribed contacts from broadcast recipients', function () {
    $group = ContactGroup::factory()->create();

    $subscribed = Contact::factory()->create(['unsubscribed_at' => null]);
    $unsubscribed = Contact::factory()->create(['unsubscribed_at' => now()]);

    $group->contacts()->attach([$subscribed->id, $unsubscribed->id]);

    $subscribedCount = $group->contacts()->subscribed()->count();

    expect($subscribedCount)->toBe(1);
    expect($group->contacts()->subscribed()->first()->id)->toBe($subscribed->id);
});

it('subscribed scope returns only contacts with null unsubscribed_at', function () {
    $subscribed = Contact::factory()->create(['unsubscribed_at' => null]);
    $unsubscribed = Contact::factory()->create(['unsubscribed_at' => now()]);

    $results = Contact::subscribed()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($subscribed->id);
});
