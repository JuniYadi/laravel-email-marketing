<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = ContactGroup::query()->get();

        if ($groups->isEmpty()) {
            $groups = ContactGroup::factory()->count(3)->create();
        }

        Contact::factory()->count(50)->create()->each(function (Contact $contact) use ($groups): void {
            $groupIds = $groups->random(fake()->numberBetween(1, min(3, $groups->count())))
                ->pluck('id');

            $contact->groups()->sync($groupIds);
        });
    }
}
