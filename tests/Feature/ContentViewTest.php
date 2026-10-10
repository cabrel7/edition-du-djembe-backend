<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Book;
use App\Models\Memoir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_article_view_increments_atomically(): void
    {
        $article = Article::factory()->create(['views_count' => 5]);

        $this->postJson('/api/updatearticleview', ['id' => $article->id])
            ->assertStatus(200);

        $this->assertSame(6, (int) $article->fresh()->views_count);
    }

    public function test_update_article_view_404_on_unknown_id(): void
    {
        $this->postJson('/api/updatearticleview', ['id' => 999999])
            ->assertStatus(404);
    }

    public function test_update_memoir_view_increments_published_only(): void
    {
        $memoir = Memoir::factory()->create(['views_count' => 2, 'status' => 1]);

        $this->postJson('/api/updatememoirview', ['id' => $memoir->id])
            ->assertStatus(200)
            ->assertJsonPath('views_count', 3);

        $draft = Memoir::factory()->create(['views_count' => 5, 'status' => 0]);
        $this->postJson('/api/updatememoirview', ['id' => $draft->id])
            ->assertStatus(404);
    }

    public function test_downloadbook_404_when_file_missing_or_unknown(): void
    {
        $this->getJson('/api/downloadbook/999999')->assertStatus(404);

        $book = Book::factory()->create();
        $this->getJson("/api/downloadbook/{$book->id}")->assertStatus(404);
    }

    public function test_downloadmemoir_404_when_unknown(): void
    {
        $this->getJson('/api/downloadmemoir/999999')->assertStatus(404);

        $memoir = Memoir::factory()->create();
        $this->getJson("/api/downloadmemoir/{$memoir->id}")->assertStatus(404);
    }
}
