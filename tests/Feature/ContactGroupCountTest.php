<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactGroupCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_returns_count_of_valid_contacts_in_group(): void
    {
        $group = ContactGroup::factory()->create();

        // Valid contacts
        Contact::factory()->count(10)->create(['is_invalid' => false])
            ->each(fn ($c) => $c->groups()->attach($group));

        // Invalid contacts (should be excluded)
        Contact::factory()->count(3)->create(['is_invalid' => true])
            ->each(fn ($c) => $c->groups()->attach($group));

        $response = $this->getJson(route('contacts.groups.count', $group));

        $response->assertOk();
        $response->assertJson(['count' => 10]);
    }

    public function test_count_requires_authentication(): void
    {
        $this->app['auth']->guard()->logout();

        $group = ContactGroup::factory()->create();

        $response = $this->getJson(route('contacts.groups.count', $group));

        $response->assertUnauthorized();
    }
}
