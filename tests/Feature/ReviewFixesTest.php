<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Book;
use App\Models\Categorie;
use App\Models\Domaine;
use App\Models\PressReview;
use App\Models\Team_members;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Correctifs issus de la revue : nettoyage des fichiers orphelins, destroy sûr, slug catégorie,
 * plafond de mémorisation de l'idempotence. Vraie base (sqlite) et vrai disque ; l'échec
 * d'écriture est provoqué par un événement de modèle (aucune couche mockée).
 */
class ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = '7d9c1f6e-2f0b-4c63-9a53-0a0e2f6d8b22';

    /** @var string[] */
    private array $createdFiles = [];

    /** @var array<string, string[]> */
    private array $snapshots = [];

    private const WATCHED = [
        'press-reviews/covers', 'press-reviews/gallery', 'press-reviews/documents',
        'articles', 'team',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::WATCHED as $dir) {
            $this->snapshots[$dir] = glob(public_path($dir . '/*')) ?: [];
        }
        $this->snapshots['private-books'] = glob(storage_path('app/private/books/documents/*')) ?: [];
        $this->snapshots['private-extrait'] = glob(storage_path('app/private/books/extrait_documents/*')) ?: [];
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            File::delete($file);
        }
        foreach (self::WATCHED as $dir) {
            foreach (array_diff(glob(public_path($dir . '/*')) ?: [], $this->snapshots[$dir]) as $leak) {
                File::delete($leak);
            }
        }
        foreach (array_diff(glob(storage_path('app/private/books/documents/*')) ?: [], $this->snapshots['private-books']) as $leak) {
            File::delete($leak);
        }
        foreach (array_diff(glob(storage_path('app/private/books/extrait_documents/*')) ?: [], $this->snapshots['private-extrait']) as $leak) {
            File::delete($leak);
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 1]);
        Passport::actingAs($user);

        return $user;
    }

    private function png(int $shade): UploadedFile
    {
        $img = imagecreatetruecolor(4, 4);
        imagefill($img, 0, 0, imagecolorallocate($img, $shade, 10, 10));
        ob_start();
        imagepng($img);

        return UploadedFile::fake()->createWithContent("img{$shade}.png", (string) ob_get_clean());
    }

    private function putPublic(string $relative, string $content = 'x'): string
    {
        $path = public_path($relative);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $content);
        $this->createdFiles[] = $path;

        return $path;
    }

    private function assertNoNewFiles(): void
    {
        foreach (self::WATCHED as $dir) {
            $this->assertSame([], array_values(array_diff(glob(public_path($dir . '/*')) ?: [], $this->snapshots[$dir])), "Fichier orphelin dans public/{$dir}");
        }
        $this->assertSame([], array_values(array_diff(glob(storage_path('app/private/books/documents/*')) ?: [], $this->snapshots['private-books'])));
        $this->assertSame([], array_values(array_diff(glob(storage_path('app/private/books/extrait_documents/*')) ?: [], $this->snapshots['private-extrait'])));
    }

    private function reviewPayload(array $extra = []): array
    {
        return array_merge([
            'title' => 'Revue',
            'summary' => 'Résumé',
            'content' => '<p>Contenu</p>',
            'category' => 'article',
            'status' => 'draft',
        ], $extra);
    }

    // ============ (a) échec en base après upload => aucun fichier orphelin ============

    public function test_a_press_review_store_db_failure_removes_new_files(): void
    {
        $this->admin();
        PressReview::creating(function (): void {
            throw new \RuntimeException('db down');
        });

        $response = $this->call('POST', '/api/admin/press-reviews', $this->reviewPayload(), [], [
            'cover_image' => $this->png(1),
            'gallery_images' => [$this->png(2), $this->png(3)],
            'documents' => [UploadedFile::fake()->create('d.pdf', 5, 'application/pdf')],
        ], ['HTTP_ACCEPT' => 'application/json']);

        $response->assertStatus(500)->assertJson(['success' => false]);
        $this->assertSame(0, PressReview::count());
        $this->assertNoNewFiles();
    }

    public function test_a_press_review_update_db_failure_removes_only_new_files_and_keeps_old(): void
    {
        $this->admin();
        $old = $this->putPublic('press-reviews/covers/old-cover.png', 'old');
        $review = PressReview::create($this->reviewPayload(['slug' => 'r1', 'cover_image' => 'press-reviews/covers/old-cover.png']));
        $this->snapshots['press-reviews/covers'][] = $old;
        PressReview::updating(function (): void {
            throw new \RuntimeException('db down');
        });

        $this->call('PUT', "/api/admin/press-reviews/{$review->id}", ['title' => 'Nouveau'], [], [
            'cover_image' => $this->png(4),
            'gallery_images' => [$this->png(5)],
        ], ['HTTP_ACCEPT' => 'application/json'])->assertStatus(500);

        $this->assertFileExists($old);
        $this->assertSame('old-cover.png', basename((string) $review->fresh()->cover_image));
        $this->assertNoNewFiles();
    }

    public function test_a_article_create_db_failure_removes_uploaded_image(): void
    {
        $this->admin();
        Article::creating(function (): void {
            throw new \RuntimeException('db down');
        });

        $this->post('/api/saveArticle', ['title' => 'T', 'excerpt' => 'E', 'content' => 'C', 'image' => $this->png(6)], ['Accept' => 'application/json'])
            ->assertStatus(500);

        $this->assertSame(0, Article::count());
        $this->assertNoNewFiles();
    }

    public function test_a_book_private_document_removed_on_db_failure(): void
    {
        $this->admin();
        Book::creating(function (): void {
            throw new \RuntimeException('db down');
        });

        $this->post('/api/saveBook', [
            'title' => 'Livre', 'author' => 'A', 'description' => 'D', 'price' => 10, 'isbn' => '978-0-00000-000-0', 'pages' => 10,
            'category' => Categorie::factory()->create()->id,
            'document' => UploadedFile::fake()->create('l.pdf', 5, 'application/pdf'),
            'extraitdocument' => UploadedFile::fake()->create('e.pdf', 5, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(500);

        $this->assertNoNewFiles();
    }

    public function test_a_team_member_photo_removed_on_db_failure(): void
    {
        $this->admin();
        Team_members::creating(function (): void {
            throw new \RuntimeException('db down');
        });

        $this->post('/api/team_members', ['nom' => 'N', 'prenom' => 'P', 'role' => 'R', 'photo' => $this->png(7)], ['Accept' => 'application/json'])
            ->assertStatus(500);

        $this->assertNoNewFiles();
    }

    public function test_a_successful_save_keeps_new_files(): void
    {
        $this->admin();

        $this->post('/api/saveArticle', ['title' => 'T', 'excerpt' => 'E', 'content' => 'C', 'image' => $this->png(8)], ['Accept' => 'application/json'])
            ->assertOk();

        $image = Article::firstOrFail()->image;
        $this->assertFileExists(public_path('articles/' . $image));
    }

    // ============ (b) destroy : fichiers supprimés de façon sûre ============

    public function test_b_destroy_removes_cover_gallery_and_documents(): void
    {
        $this->admin();
        $cover = $this->putPublic('press-reviews/covers/c1.png');
        $gal = $this->putPublic('press-reviews/gallery/g1.png');
        $doc = $this->putPublic('press-reviews/documents/d1.pdf');
        $review = PressReview::create($this->reviewPayload([
            'slug' => 'r2',
            'cover_image' => 'press-reviews/covers/c1.png',
            'gallery_images' => ['press-reviews/gallery/g1.png'],
            'documents' => [['path' => 'press-reviews/documents/d1.pdf', 'name' => 'd.pdf']],
        ]));

        $this->deleteJson("/api/admin/press-reviews/{$review->id}")->assertOk();

        $this->assertNull(PressReview::find($review->id));
        $this->assertFileDoesNotExist($cover);
        $this->assertFileDoesNotExist($gal);
        $this->assertFileDoesNotExist($doc);
    }

    public function test_b_destroy_ignores_traversal_and_foreign_directory_values(): void
    {
        $this->admin();
        $decoy = $this->putPublic('decoy-review-fix.txt', 'keep');
        $other = $this->putPublic('articles/foreign-review-fix.png', 'keep');
        $review = PressReview::create($this->reviewPayload([
            'slug' => 'r3',
            'cover_image' => 'press-reviews/covers/../../decoy-review-fix.txt',
            'gallery_images' => ['../articles/foreign-review-fix.png'],
            'documents' => ['https://evil.example/x.pdf', ['path' => '../../decoy-review-fix.txt']],
        ]));

        $this->deleteJson("/api/admin/press-reviews/{$review->id}")->assertOk();

        $this->assertNull(PressReview::find($review->id));
        $this->assertFileExists($decoy);
        $this->assertFileExists($other);
    }

    public function test_b_destroy_unknown_id_is_404(): void
    {
        $this->admin();
        $this->deleteJson('/api/admin/press-reviews/987654')->assertStatus(404);
    }

    public function test_b_destroy_requires_admin(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 0]));
        $review = PressReview::create($this->reviewPayload(['slug' => 'r4']));

        $this->deleteJson("/api/admin/press-reviews/{$review->id}")->assertStatus(403);
        $this->assertNotNull(PressReview::find($review->id));
    }

    // ============ (c) slug de catégorie ============

    public function test_c_category_gets_unique_slug_on_create(): void
    {
        $this->admin();

        $first = $this->postJson('/api/saveCategorie', ['name' => 'Poésie Africaine'])->assertOk()->json('categorie.slug');
        $second = $this->postJson('/api/saveCategorie', ['name' => 'Poésie Africaine'])->assertOk()->json('categorie.slug');

        $this->assertSame('poesie-africaine', $first);
        $this->assertSame('poesie-africaine-2', $second);
    }

    public function test_c_category_slug_is_stable_on_rename_and_backfilled_when_missing(): void
    {
        $this->admin();
        $id = (int) $this->postJson('/api/saveCategorie', ['name' => 'Roman'])->json('categorie.id');
        $this->postJson('/api/saveCategorie', ['id' => $id, 'name' => 'Romans'])->assertOk();
        $this->assertSame('roman', Categorie::findOrFail($id)->slug);

        $legacy = Categorie::create(['name' => 'Essai', 'type' => 'book']);
        $this->assertNull($legacy->fresh()->slug);
        $this->postJson('/api/saveCategorie', ['id' => $legacy->id, 'description' => 'x'])->assertOk();
        $this->assertSame('essai', $legacy->fresh()->slug);
    }

    // ============ idempotence : plafond de taille mémorisée ============

    public function test_idempotency_does_not_memoize_oversized_response(): void
    {
        $this->admin();
        config(['idempotency.max_body_bytes' => 1, 'idempotency.lock_wait_seconds' => 0]);

        $headers = ['Idempotency-Key' => self::KEY];
        $this->postJson('/api/saveDomaine', ['name' => 'D'], $headers)->assertOk();
        $second = $this->postJson('/api/saveDomaine', ['name' => 'D'], $headers)->assertOk();

        $this->assertNull($second->headers->get('Idempotent-Replayed'));
        $this->assertSame(2, Domaine::count());
    }

    public function test_idempotency_replays_small_response(): void
    {
        $this->admin();
        config(['idempotency.lock_wait_seconds' => 0]);

        $headers = ['Idempotency-Key' => self::KEY];
        $this->postJson('/api/saveDomaine', ['name' => 'D'], $headers)->assertOk();
        $second = $this->postJson('/api/saveDomaine', ['name' => 'D'], $headers)->assertOk();

        $this->assertSame('true', $second->headers->get('Idempotent-Replayed'));
        $this->assertSame(1, Domaine::count());
    }
}
