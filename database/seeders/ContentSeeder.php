<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Book;
use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Domaine;
use App\Models\Memoir;
use App\Models\Submission;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Categorie::factory()->count(5)->create();

        Domaine::factory()->count(4)->create();

        Book::factory()->count(12)->create([
            'category' => $categories->random()->id,
        ]);

        Article::factory()->count(8)->create();

        Memoir::factory()->count(6)->create();

        Submission::factory()->count(10)->create();

        Contact::factory()->count(5)->create();
    }
}
