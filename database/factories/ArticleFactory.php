<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = ucfirst(fake()->unique()->words(4, true));

        return [
            'title' => $title,
            'excerpt' => fake()->sentence(),
            'content' => fake()->paragraphs(5, true),
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(6)),
            'author' => fake()->name(),
            'published_date' => fake()->date(),
            'views_count' => fake()->numberBetween(0, 500),
            'status' => 1,
        ];
    }
}
