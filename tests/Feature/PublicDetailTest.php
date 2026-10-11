<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Memoir;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_detail_resolves_by_slug(): void
    {
        $book = Book::factory()->create(['status' => 1, 'slug' => 'mon-livre-test']);

        $this->getJson('/api/books/mon-livre-test')
            ->assertOk()
            ->assertJsonPath('id', $book->id)
            ->assertJsonPath('slug', 'mon-livre-test');
    }

    public function test_book_detail_resolves_by_id(): void
    {
        $book = Book::factory()->create(['status' => 1]);

        $this->getJson('/api/books/' . $book->id)->assertOk()->assertJsonPath('id', $book->id);
    }

    public function test_book_detail_hides_drafts_and_404s(): void
    {
        Book::factory()->create(['status' => 0, 'slug' => 'brouillon']);

        $this->getJson('/api/books/brouillon')->assertNotFound();
        $this->getJson('/api/books/inexistant')->assertNotFound();
    }

    public function test_memoir_and_video_detail_by_slug(): void
    {
        $m = Memoir::factory()->create(['status' => 1, 'slug' => 'memoire-test']);
        $v = Video::create([
            'status' => 1, 'slug' => 'video-test', 'title' => 'V',
            'description' => 'Desc', 'thumbnail' => 'thumb.jpg', 'video_url' => 'https://youtu.be/x',
        ]);

        $this->getJson('/api/memoirs/memoire-test')->assertOk()->assertJsonPath('id', $m->id);
        $this->getJson('/api/videos/video-test')->assertOk()->assertJsonPath('id', $v->id);
    }
}
