<?php

namespace Database\Factories;

use App\Models\PrivacyPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrivacyPolicy>
 */
class PrivacyPolicyFactory extends Factory
{
    protected $model = PrivacyPolicy::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'content' => implode("\n\n", fake()->paragraphs(10)),
            'is_active' => false,
            'effective_date' => fake()->dateTimeBetween('-1 year', '+1 year'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
