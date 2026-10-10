<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_healthcheck(): void
    {
        $this->getJson('/up')->assertStatus(200)->assertJsonPath('status', 'ok');
    }

    public function test_boot_excludes_drafts(): void
    {
        Book::factory()->create(['title' => 'Publié', 'status' => 1]);
        Book::factory()->create(['title' => 'Brouillon', 'status' => 0]);
        Article::factory()->create(['title' => 'Article publié', 'status' => 1]);
        Article::factory()->create(['title' => 'Article brouillon', 'status' => 0]);

        $response = $this->getJson('/api/data/null');

        $response->assertStatus(200);
        $titles = collect($response->json('books'))->pluck('title');
        $this->assertTrue($titles->contains('Publié'));
        $this->assertFalse($titles->contains('Brouillon'), 'Les livres brouillons ne doivent pas être exposés publiquement');

        $articleTitles = collect($response->json('articles'))->pluck('title');
        $this->assertTrue($articleTitles->contains('Article publié'));
        $this->assertFalse($articleTitles->contains('Article brouillon'));
    }

    public function test_boot_omits_heavy_file_columns(): void
    {
        Book::factory()->create(['status' => 1]);

        $response = $this->getJson('/api/data/null');

        $book = $response->json('books.0');
        $this->assertArrayNotHasKey('document', $book);
        $this->assertArrayNotHasKey('extraitdocument', $book);
        $this->assertArrayHasKey('title', $book);
    }

    public function test_draft_download_is_404(): void
    {
        $book = Book::factory()->create(['status' => 0]);

        $this->getJson("/api/downloadbook/{$book->id}")->assertStatus(404);
    }

    public function test_security_headers_present(): void
    {
        $response = $this->get('/up');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 6) as $i) {
            $this->postJson('/api/login', ['email' => 'x@example.com', 'password' => 'bad'])
                ->assertStatus(401);
        }

        $this->postJson('/api/login', ['email' => 'x@example.com', 'password' => 'bad'])
            ->assertStatus(429);
    }
}
