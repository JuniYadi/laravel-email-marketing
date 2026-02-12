<?php

namespace Database\Factories;

use App\Models\Broadcast;
use App\Models\ContactGroup;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Broadcast>
 */
class BroadcastFactory extends Factory
{
    protected $model = Broadcast::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'contact_group_id' => ContactGroup::factory(),
            'email_template_id' => EmailTemplate::factory(),
            'status' => Broadcast::STATUS_SCHEDULED,
            'starts_at' => now()->addHour(),
            'messages_per_minute' => 1,
            'reply_to' => fake()->safeEmail(),
            'from_name' => fake()->company(),
            'from_prefix' => fake()->userName(),
            'from_domain' => 'marketing.test.com',
            'from_email' => null,
            'snapshot_subject' => null,
            'snapshot_html_content' => null,
            'snapshot_builder_schema' => null,
            'snapshot_template_version' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
