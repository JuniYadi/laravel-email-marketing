<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'subject' => 'Hello {{ first_name }}',
            'html_content' => '<h1>Hello {{ first_name }}</h1><p>Welcome to {{ company }}</p>',
            'builder_schema' => null,
            'is_active' => true,
            'version' => 1,
        ];
    }
}
