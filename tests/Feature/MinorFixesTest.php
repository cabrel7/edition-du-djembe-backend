<?php

namespace Tests\Feature;

use App\Casts\LenientJsonArray;
use App\Models\Book;
use App\Models\Memoir;
use App\Models\Partners;
use App\Models\PressReview;
use App\Models\Service;
use App\Models\Submission;
use App\Models\Team_members;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MinorFixesTest extends TestCase
{
    use RefreshDatabase;

    /** Répertoires publics surveillés : aucun fichier de test ne doit y subsister. */
    private const WATCHED_PUBLIC = [
        'articles', 'books/covers', 'books/documents', 'books/extrait_documents',
        'memoirs/covers', 'memoirs/documents', 'video', 'service', 'partners', 'team',
        'press-reviews/covers', 'press-reviews/documents', 'press-reviews/gallery',
    ];

    /** @var array<string, string[]> */
    private array $snapshot = [];

    /** @var string[] */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->snapshot = $this->listing();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            File::delete($file);
        }
        $after = $this->listing();
        $leaks = [];
        foreach ($after as $dir => $files) {
            foreach (array_diff($files, $this->snapshot[$dir] ?? []) as $leak) {
                $leaks[] = $leak;
                File::delete($leak);
            }
        }
        parent::tearDown();
        $this->assertSame([], $leaks, 'Fichiers de test laissés dans public/');
    }

    /** @return array<string, string[]> */
    private function listing(): array
    {
        $out = [];
        foreach (self::WATCHED_PUBLIC as $dir) {
            $out[$dir] = glob(public_path($dir . '/*')) ?: [];
        }

        return $out;
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 1]);
        Passport::actingAs($user);

        return $user;
    }

    private function file(string $path, string $content = '%PDF-1.4 x'): string
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0750, true);
        }
        file_put_contents($path, $content);
        $this->createdFiles[] = $path;

        return $path;
    }

    private function review(array $extra = []): PressReview
    {
        return PressReview::create(array_merge([
            'title' => 'T', 'slug' => 't-' . uniqid(), 'summary' => 's', 'content' => 'c',
            'category' => 'article', 'status' => 'published', 'published_at' => now(),
        ], $extra));
    }

    private function reviewPayload(array $extra = []): array
    {
        return array_merge(['title' => 'Rev', 'summary' => 's', 'content' => 'c', 'category' => 'article', 'status' => 'draft'], $extra);
    }

    // ============ 1. LenientJsonArray ============

    private function castSet(mixed $value): ?string
    {
        return (new LenientJsonArray())->set(new Memoir(), 'keywords', $value, []);
    }

    public function test_cast_array_is_encoded_once(): void
    {
        $this->assertSame('["a","b"]', $this->castSet(['a', 'b']));
    }

    public function test_cast_json_array_string_is_normalised(): void
    {
        $this->assertSame('["a","b"]', $this->castSet('["a","b"]'));
        $this->assertSame('["a"]', $this->castSet(json_encode(json_encode(['a']))));
    }

    public function test_cast_plain_string_is_kept_as_single_item_list(): void
    {
        $stored = $this->castSet('foo');
        $this->assertSame('["foo"]', $stored);
        $this->assertSame(['foo'], LenientJsonArray::decode($stored));
    }

    public function test_cast_null_and_blank_give_null(): void
    {
        $this->assertNull($this->castSet(null));
        $this->assertNull($this->castSet(''));
        $this->assertNull($this->castSet('   '));
    }

    public function test_cast_rejects_scalars_other_than_strings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->castSet(42);
    }

    public function test_memoir_string_keywords_round_trip_through_model(): void
    {
        $memoir = Memoir::factory()->create(['keywords' => 'foo']);
        $this->assertSame(['foo'], $memoir->fresh()->keywords);
    }

    // ============ 2. Suppression des fichiers ============

    public function test_delete_team_member_removes_photo(): void
    {
        $this->admin();
        $path = $this->file(public_path('team/zz-minor-photo.jpg'), 'img');
        $member = Team_members::create(['nom' => 'N', 'prenom' => 'P', 'role' => 'r', 'photo' => 'zz-minor-photo.jpg', 'is_active' => true]);

        $this->deleteJson("/api/team_members/{$member->id}")->assertOk();

        $this->assertDatabaseMissing('team_members', ['id' => $member->id]);
        $this->assertFileDoesNotExist($path);
    }

    public function test_delete_partner_removes_logo_and_ignores_external_url(): void
    {
        $this->admin();
        $path = $this->file(public_path('partners/zz-minor-logo.png'), 'img');
        $partner = Partners::create(['nom' => 'P', 'image' => 'zz-minor-logo.png', 'is_active' => true]);
        $external = Partners::create(['nom' => 'E', 'image' => 'https://cdn.example.com/x.png', 'is_active' => true]);

        $this->deleteJson("/api/partners/{$partner->id}")->assertOk();
        $this->deleteJson("/api/partners/{$external->id}")->assertOk();

        $this->assertFileDoesNotExist($path);
    }

    public function test_delete_memoir_removes_cover_and_private_document(): void
    {
        $this->admin();
        $cover = $this->file(public_path('memoirs/covers/zz-minor-cover.jpg'), 'img');
        $doc = $this->file(storage_path('app/private/memoirs/documents/zz-minor-doc.pdf'));
        $memoir = Memoir::factory()->create(['cover' => 'zz-minor-cover.jpg', 'document' => 'zz-minor-doc.pdf']);

        $this->deleteJson("/api/deleteMemoir/{$memoir->id}")->assertOk();

        $this->assertFileDoesNotExist($cover);
        $this->assertFileDoesNotExist($doc);
    }

    public function test_delete_video_and_service_remove_their_image(): void
    {
        $this->admin();
        $thumb = $this->file(public_path('video/zz-minor-thumb.jpg'), 'img');
        $img = $this->file(public_path('service/zz-minor-svc.jpg'), 'img');
        $video = Video::create(['title' => 'V', 'description' => 'd', 'video_url' => 'https://x.test/v', 'thumbnail' => 'zz-minor-thumb.jpg', 'slug' => 'v-' . uniqid(), 'status' => 1]);
        $service = Service::create(['title' => 'S', 'description' => 'd', 'image' => 'zz-minor-svc.jpg', 'slug' => 's-' . uniqid(), 'is_active' => true]);

        $this->deleteJson("/api/deleteVideo/{$video->id}")->assertOk();
        $this->deleteJson("/api/deleteService/{$service->id}")->assertOk();

        $this->assertFileDoesNotExist($thumb);
        $this->assertFileDoesNotExist($img);
    }

    public function test_delete_book_is_soft_and_keeps_files_for_restoration(): void
    {
        $this->admin();
        $doc = $this->file(storage_path('app/private/books/documents/zz-minor-book.pdf'));
        $book = Book::factory()->create(['document' => 'zz-minor-book.pdf']);

        $this->deleteJson("/api/deleteBook/{$book->id}")->assertOk();

        $this->assertSoftDeleted('books', ['id' => $book->id]);
        $this->assertFileExists($doc);
    }

    // ============ 4. Tri / pagination ============

    public function test_public_index_ignores_unknown_order_by_and_caps_per_page(): void
    {
        $this->review();

        $this->getJson('/api/press-reviews?order_by=' . urlencode('id; DROP TABLE users--') . '&order_dir=sideways')
            ->assertOk();
        $res = $this->getJson('/api/press-reviews?per_page=100000')->assertOk();
        $this->assertSame(50, $res->json('data.pagination.per_page'));
        $res = $this->getJson('/api/press-reviews?per_page=0')->assertOk();
        $this->assertSame(1, $res->json('data.pagination.per_page'));
    }

    public function test_admin_index_ignores_unknown_order_by_and_caps_per_page(): void
    {
        $this->admin();
        $this->review();

        $this->getJson('/api/admin/press-reviews?order_by=password')->assertOk();
        $res = $this->getJson('/api/admin/press-reviews?per_page=100000')->assertOk();
        $this->assertSame(50, $res->json('data.pagination.per_page'));
    }

    public function test_sort_by_whitelisted_column_ascending_works(): void
    {
        $this->review(['title' => 'B']);
        $this->review(['title' => 'A']);

        $titles = collect($this->getJson('/api/press-reviews?order_by=title&order_dir=asc')->json('data.press_reviews'))->pluck('title')->all();
        $this->assertSame(['A', 'B'], $titles);
    }

    public function test_contact_index_per_page_is_capped(): void
    {
        $this->admin();
        $res = $this->getJson('/api/contacts?per_page=100000')->assertOk();
        $this->assertSame(50, $res->json('pagination.per_page'));
    }

    // ============ 5. nosniff sur les téléchargements ============

    public function test_downloads_carry_nosniff(): void
    {
        $this->admin();
        $this->file(storage_path('app/private/memoirs/documents/zz-minor-dl.pdf'));
        $memoir = Memoir::factory()->create(['status' => 1, 'document' => 'zz-minor-dl.pdf']);
        $this->file(storage_path('app/private/books/extrait_documents/zz-minor-ex.pdf'));
        $book = Book::factory()->create(['status' => 1, 'extraitdocument' => 'zz-minor-ex.pdf']);
        $this->file(storage_path('app/submissions/zz-minor-sub.pdf'));
        $submission = Submission::factory()->create(['file_path' => 'zz-minor-sub.pdf']);

        foreach ([
            "/api/downloadmemoir/{$memoir->id}",
            "/api/downloadbook/{$book->id}",
            "/api/admin/memoirs/{$memoir->id}/download",
            "/api/admin/submissions/{$submission->id}/download",
        ] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'), $url);
        }
    }

    // ============ 8. Tags des revues de presse ============

    public function test_store_with_tags_array(): void
    {
        $this->admin();
        $res = $this->postJson('/api/admin/press-reviews', $this->reviewPayload(['tags' => ['a', ' b ', 'a', '']]))->assertStatus(201);
        $this->assertSame(['a', 'b'], PressReview::find($res->json('data.id'))->tags);
    }

    public function test_update_tags_present_without_tags_clears_them(): void
    {
        $this->admin();
        $review = $this->review(['tags' => ['x', 'y']]);

        $this->putJson("/api/admin/press-reviews/{$review->id}", ['tags_present' => 1])->assertOk();

        $this->assertSame([], $review->fresh()->tags);
    }

    public function test_update_without_tags_nor_marker_keeps_tags(): void
    {
        $this->admin();
        $review = $this->review(['tags' => ['x', 'y']]);

        $this->putJson("/api/admin/press-reviews/{$review->id}", ['title' => 'Nouveau'])->assertOk();

        $this->assertSame(['x', 'y'], $review->fresh()->tags);
    }

    public function test_update_with_tags_replaces_them(): void
    {
        $this->admin();
        $review = $this->review(['tags' => ['x', 'y']]);

        $this->putJson("/api/admin/press-reviews/{$review->id}", ['tags_present' => 1, 'tags' => ['z']])->assertOk();

        $this->assertSame(['z'], $review->fresh()->tags);
    }

    public function test_too_long_tag_or_too_many_tags_give_422(): void
    {
        $this->admin();
        $review = $this->review();

        $this->putJson("/api/admin/press-reviews/{$review->id}", ['tags' => [str_repeat('a', 51)]])
            ->assertStatus(422)->assertJsonValidationErrors(['tags.0']);
        $this->postJson('/api/admin/press-reviews', $this->reviewPayload(['tags' => array_map('strval', range(1, 21))]))
            ->assertStatus(422)->assertJsonValidationErrors(['tags']);
    }
}
