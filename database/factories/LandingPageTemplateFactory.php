<?php

namespace Database\Factories;

use App\Models\LandingPageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LandingPageTemplate>
 */
class LandingPageTemplateFactory extends Factory
{
    protected $model = LandingPageTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = Str::slug(fake()->unique()->words(2, true));

        return [
            'key' => $key,
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'view_path' => 'landing-page-templates.'.$key.'.view',
            'schema' => [
                'fields' => [
                    [
                        'key' => 'headline',
                        'label' => 'Headline',
                        'type' => 'text',
                        'required' => true,
                        'max' => 120,
                        'default' => 'Launch your next event',
                    ],
                ],
            ],
            'preview_image_url' => 'https://placehold.co/1200x800?text=Landing+Preview',
            'is_active' => true,
            'version' => 1,
        ];
    }
}
