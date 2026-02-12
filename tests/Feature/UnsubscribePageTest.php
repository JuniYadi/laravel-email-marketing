<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('shows confirmation for valid signed url', function () {
    $contact = Contact::factory()->create([
        'email' => 'test@example.com',
        'first_name' => 'John',
    ]);

    $url = URL::signedRoute('unsubscribe', ['contact' => $contact->id]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Unsubscribe')
        ->assertSee($contact->email);
});

it('marks contact as unsubscribed when confirmed', function () {
    $contact = Contact::factory()->create([
        'email' => 'test@example.com',
    ]);

    expect($contact->isUnsubscribed())->toBeFalse();

    Livewire::test('pages::unsubscribe.show', ['contact' => $contact])
        ->call('unsubscribe')
        ->assertSet('unsubscribed', true);

    $contact->refresh();
    expect($contact->isUnsubscribed())->toBeTrue();
});

it('rejects expired signed urls', function () {
    $contact = Contact::factory()->create();

    $url = URL::temporarySignedRoute('unsubscribe', now()->subMinutes(1), ['contact' => $contact->id]);

    $this->get($url)
        ->assertForbidden();
});

it('rejects invalid signatures', function () {
    $contact = Contact::factory()->create();

    $url = route('unsubscribe', ['contact' => $contact->id]).'?signature=invalid';

    $this->get($url)
        ->assertForbidden();
});

it('shows already unsubscribed message for unsubscribed contacts', function () {
    $contact = Contact::factory()->create([
        'unsubscribed_at' => now(),
    ]);

    $url = URL::signedRoute('unsubscribe', ['contact' => $contact->id]);

    $this->get($url)
        ->assertOk()
        ->assertSee('already unsubscribed');
});
