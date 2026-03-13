<?php

namespace Database\Factories;

use App\Models\LandingPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LandingPageView>
 */
class LandingPageViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'landing_page_id' => LandingPage::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'is_bot' => false,
            'viewed_at' => now(),
        ];
    }
}
