<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Memoir>
 */
class MemoirFactory extends Factory
{
    public function definition(): array
    {
        $title = ucfirst(fake()->unique()->words(4, true));

        return [
            'title' => $title,
            'author' => fake()->name(),
            'university' => fake()->company() . ' University',
            'domain' => \App\Models\Domaine::factory(),
            'year' => fake()->numberBetween(2015, 2026),
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(6)),
            'abstract' => fake()->paragraphs(2, true),
            'keywords' => fake()->words(5, true),
            'supervisor' => fake()->name(),
            'grade' => fake()->randomElement(['Très bien', 'Bien', 'Passable']),
            'views_count' => fake()->numberBetween(0, 300),
            'status' => 1,
        ];
    }
}
