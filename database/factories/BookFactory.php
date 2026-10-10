<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        $title = ucfirst(fake()->unique()->words(3, true));

        return [
            'title' => $title,
            'author' => fake()->name(),
            'category' => \App\Models\Categorie::factory(),
            'cover' => 'books/covers/' . Str::random(20) . '.jpg',
            'description' => fake()->paragraphs(3, true),
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(6)),
            'published_date' => fake()->date(),
            'price' => fake()->randomFloat(2, 5, 45),
            'isbn' => fake()->unique()->isbn13(),
            'amazon_url' => 'https://www.amazon.fr/dp/' . fake()->bothify('##########'),
            'pages' => fake()->numberBetween(80, 500),
            'document' => 'books/documents/' . Str::random(20) . '.pdf',
            'extraitdocument' => 'books/extrait_documents/' . Str::random(20) . '.pdf',
            'stock_quantity' => fake()->numberBetween(0, 100),
            'language' => fake()->randomElement(['fr', 'en']),
            'publisher' => fake()->company(),
            'edition' => (string) fake()->numberBetween(1, 10),
            'rating' => fake()->randomFloat(2, 0, 5),
            'status' => 1,
        ];
    }

    public function withoutAmazon(): static
    {
        return $this->state(fn () => ['amazon_url' => null]);
    }
}
