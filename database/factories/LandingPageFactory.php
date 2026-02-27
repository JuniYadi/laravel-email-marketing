<?php

namespace Database\Factories;

use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LandingPage>
 */
class LandingPageFactory extends Factory
{
    protected $model = LandingPage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);
        $slug = Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999);

        return [
            'user_id' => User::factory(),
            'landing_page_template_id' => LandingPageTemplate::factory(),
            'title' => $title,
            'slug' => $slug,
            'custom_domain' => null,
            'status' => LandingPage::STATUS_DRAFT,
            'meta' => [
                'title' => $title,
                'description' => fake()->sentence(),
                'og_title' => $title,
                'og_description' => fake()->sentence(),
                'og_image' => 'https://placehold.co/1200x630?text=OG',
                'noindex' => false,
            ],
            'form_data' => [
                'headline' => 'Join our launch',
            ],
            'template_snapshot' => [
                'key' => 'basic',
                'name' => 'Basic',
                'description' => 'Base landing page template',
                'view_path' => 'landing-page-templates.basic.view',
                'version' => 1,
                'schema' => [
                    'fields' => [
                        [
                            'key' => 'headline',
                            'label' => 'Headline',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'key' => 'body',
                            'label' => 'Body',
                            'type' => 'textarea',
                            'required' => true,
                        ],
                        [
                            'key' => 'cta_label',
                            'label' => 'CTA Label',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'key' => 'cta_url',
                            'label' => 'CTA URL',
                            'type' => 'url',
                            'required' => true,
                        ],
                    ],
                ],
            ],
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => LandingPage::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
