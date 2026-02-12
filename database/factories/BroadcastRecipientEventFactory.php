<?php

namespace Database\Factories;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastRecipientEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BroadcastRecipientEvent>
 */
class BroadcastRecipientEventFactory extends Factory
{
    protected $model = BroadcastRecipientEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'broadcast_id' => Broadcast::factory(),
            'broadcast_recipient_id' => BroadcastRecipient::factory(),
            'provider_message_id' => fake()->uuid(),
            'event_type' => BroadcastRecipientEvent::TYPE_DELIVERY,
            'payload' => ['eventType' => 'Delivery'],
            'occurred_at' => now(),
        ];
    }
}
