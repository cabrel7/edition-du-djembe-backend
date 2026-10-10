<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Submission>
 */
class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['book', 'memoir', 'article']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'title' => ucfirst(fake()->words(4, true)),
            'summary' => fake()->paragraphs(2, true),
            'file_path' => \Illuminate\Support\Str::random(20) . '.pdf',
            'file_name' => fake()->word() . '.pdf',
            'rgpd_consent' => true,
            'status' => 'pending',
            'admin_notes' => null,
        ];
    }
}
