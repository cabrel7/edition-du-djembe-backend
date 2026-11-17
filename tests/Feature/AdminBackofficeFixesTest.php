<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Book;
use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Domaine;
use App\Models\Memoir;
use App\Models\PressReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Correctifs back-office issus du test E2E prod (B1..B7).
 * Base de test réelle (sqlite en mémoire) : aucune couche mockée.
 */
class AdminBackofficeFixesTest extends TestCase
{
    use RefreshDatabase;

    private const MISSING_ID = 987654;

    /** @var string[] fichiers créés pendant un test, supprimés en tearDown */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            File::delete($file);
        }
        foreach (PressReview::all() as $review) {
            foreach ((array) $review->gallery_images as $path) {
                File::delete(public_path($path));
            }
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
        $bytes = (string) ob_get_clean();

        return UploadedFile::fake()->createWithContent("img{$shade}.png", $bytes);
    }

    private function storePrivateMemoirFile(string $name, string $content = '%PDF-1.4 test'): string
    {
        $dir = storage_path('app/private/memoirs/documents');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $path = $dir . '/' . $name;
        file_put_contents($path, $content);
        $this->createdFiles[] = $path;

        return $path;
    }

    private function reviewPayload(array $extra = []): array
    {
        return array_merge([
            'title' => 'Cahier test',
            'summary' => 'Résumé',
            'content' => '<p>Contenu</p>',
            'category' => 'article',
            'status' => 'draft',
        ], $extra);
    }

    // ============ B1 : téléchargement admin d'un mémoire ============

    public function test_b1_admin_downloads_unpublished_memoir_without_counting(): void
    {
        $this->admin();
        $this->storePrivateMemoirFile('abc123.pdf');
        $memoir = Memoir::factory()->create([
            'status' => 0,
            'title' => 'Mémoire "brouillon"/évil',
            'document' => 'abc123.pdf',
            'downloads_count' => 5,
        ]);

        $response = $this->get("/api/admin/memoirs/{$memoir->id}/download");

        $response->assertStatus(200);
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
        $this->assertStringNotContainsString('/', explode(';', $disposition, 2)[1] ?? '');
        $this->assertSame(5, (int) Memoir::find($memoir->id)->downloads_count);
    }

    public function test_b1_extension_comes_from_real_file_and_legacy_path_is_basenamed(): void
    {
        $this->admin();
        $this->storePrivateMemoirFile('real.docx', 'docx-bytes');
        $memoir = Memoir::factory()->create([
            'status' => 2,
            'title' => 'Mon mémoire',
            'document' => 'memoirs/documents/real.docx',
        ]);

        $response = $this->get("/api/admin/memoirs/{$memoir->id}/download");

        $response->assertStatus(200);
        $this->assertStringContainsString('.docx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_b1_json_404_when_memoir_or_file_missing(): void
    {
        $this->admin();
        $noFile = Memoir::factory()->create(['document' => null]);
        $ghost = Memoir::factory()->create(['document' => 'ghost-file.pdf']);

        $this->get('/api/admin/memoirs/' . self::MISSING_ID . '/download')
            ->assertStatus(404)->assertJson(['success' => false]);
        $this->get("/api/admin/memoirs/{$noFile->id}/download")
            ->assertStatus(404)->assertJson(['success' => false]);
        $this->get("/api/admin/memoirs/{$ghost->id}/download")
            ->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_b1_requires_admin(): void
    {
        $memoir = Memoir::factory()->create();
        $this->getJson("/api/admin/memoirs/{$memoir->id}/download")->assertStatus(401);

        Passport::actingAs(User::factory()->create(['role' => 0]));
        $this->getJson("/api/admin/memoirs/{$memoir->id}/download")->assertStatus(403);
    }

    // ============ B2 : DELETE inexistant => 404 JSON ============

    public static function deleteRoutes(): array
    {
        return [
            'domaine' => ['/api/deleteDomaine/'],
            'categorie' => ['/api/deleteCategorie/'],
            'video' => ['/api/deleteVideo/'],
            'book' => ['/api/deleteBook/'],
            'service' => ['/api/deleteService/'],
            'memoir' => ['/api/deleteMemoir/'],
            'article' => ['/api/deleteArticle/'],
            'team' => ['/api/team_members/'],
            'partner' => ['/api/partners/'],
            'press review' => ['/api/admin/press-reviews/'],
            'contact' => ['/api/contacts/'],
            'submission' => ['/api/admin/submissions/'],
        ];
    }

    #[DataProvider('deleteRoutes')]
    public function test_b2_delete_unknown_id_is_404_json(string $prefix): void
    {
        $this->admin();

        $response = $this->deleteJson($prefix . self::MISSING_ID);
        $response->assertStatus(404)->assertJson(['success' => false])->assertJsonStructure(['success', 'message']);

        // Sans en-tête Accept : toujours du JSON pour /api/*
        $plain = $this->delete($prefix . self::MISSING_ID);
        $plain->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_b2_delete_existing_then_again_is_404(): void
    {
        $this->admin();
        $domaine = Domaine::factory()->create();
        $article = Article::factory()->create();

        $this->deleteJson("/api/deleteDomaine/{$domaine->id}")->assertStatus(200);
        $this->deleteJson("/api/deleteDomaine/{$domaine->id}")->assertStatus(404)->assertJson(['success' => false]);
        $this->deleteJson("/api/deleteArticle/{$article->id}")->assertStatus(200);
        $this->deleteJson("/api/deleteArticle/{$article->id}")->assertStatus(404)->assertJson(['success' => false]);
        $this->assertSoftDeleted('articles', ['id' => $article->id]);

        $contact = Contact::factory()->create();
        $this->deleteJson("/api/contacts/{$contact->id}")->assertStatus(200);
        $this->deleteJson("/api/contacts/{$contact->id}")->assertStatus(404)->assertJson(['success' => false]);
    }

    // ============ B3 : 401 JSON sans Accept ============

    public function test_b3_unauthenticated_api_without_accept_is_401_json(): void
    {
        foreach (['/api/getbook', '/api/team_members', '/api/admin/press-reviews'] as $uri) {
            $response = $this->get($uri);
            $response->assertStatus(401);
            $response->assertJson(['success' => false]);
            $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        }
        $this->post('/api/logout')->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_b3_cookie_token_still_authenticates_without_accept_header(): void
    {
        User::factory()->create(['email' => 'adm@example.com', 'password' => bcrypt('Secret123!'), 'role' => 1]);
        $login = $this->postJson('/api/login', ['email' => 'adm@example.com', 'password' => 'Secret123!']);
        $login->assertStatus(200);
        $token = $login->getCookie('edj_token', false)->getValue();
        $this->assertNotEmpty($token);

        // Cookie seul, sans Authorization ni Accept : authentifié (admin) => 200, pas 401.
        $this->withUnencryptedCookies(['edj_token' => $token])->get('/api/getbook')->assertStatus(200);
    }

    // ============ B4 : PUT multipart non parsé => alias POST ============

    public function test_b4_raw_multipart_put_is_not_parsed_by_php(): void
    {
        $this->admin();
        $boundary = 'XBOUNDARYX';
        $body = "--{$boundary}\r\nContent-Disposition: form-data; name=\"nom\"\r\n\r\nDupont\r\n--{$boundary}--\r\n";
        $member = \App\Models\Team_members::create(['nom' => 'A', 'prenom' => 'B', 'role' => 'C']);

        $response = $this->call('PUT', "/api/team_members/{$member->id}", [], [], [], [
            'CONTENT_TYPE' => "multipart/form-data; boundary={$boundary}",
            'HTTP_ACCEPT' => 'application/json',
        ], $body);

        $response->assertStatus(422);
        $this->assertArrayHasKey('nom', $response->json('errors'));
    }

    public function test_b4_post_alias_updates_team_member_and_partner(): void
    {
        $this->admin();
        $member = \App\Models\Team_members::create(['nom' => 'A', 'prenom' => 'B', 'role' => 'C']);
        $partner = \App\Models\Partners::create(['nom' => 'P']);

        $this->post("/api/team_members/{$member->id}", [
            'nom' => 'Nouveau', 'prenom' => 'Prénom', 'role' => 'Rôle', 'is_active' => 1,
        ], ['Accept' => 'application/json'])
            ->assertStatus(200)->assertJsonPath('team_member.id', $member->id);
        $this->assertDatabaseHas('team_members', ['id' => $member->id, 'nom' => 'Nouveau']);
        $this->assertSame(1, \App\Models\Team_members::count());

        $this->post("/api/partners/{$partner->id}", ['nom' => 'Renommé', 'is_active' => 1], ['Accept' => 'application/json'])
            ->assertStatus(200)->assertJsonPath('partner.id', $partner->id);
        $this->assertDatabaseHas('partners', ['id' => $partner->id, 'nom' => 'Renommé']);
        $this->assertSame(1, \App\Models\Partners::count());

        $this->postJson('/api/partners/' . self::MISSING_ID, ['nom' => 'X'])->assertStatus(404);
    }

    // ============ B5 : galerie idempotente ============

    public function test_b5_same_file_sent_twice_gives_one_image(): void
    {
        $this->admin();
        $before = count(glob(public_path('press-reviews/gallery/*')) ?: []);
        $review = PressReview::create($this->reviewPayload(['slug' => 'cahier-test']));

        $first = $this->call('PUT', "/api/admin/press-reviews/{$review->id}", [], [], [
            'gallery_images' => [$this->png(40)],
        ], ['HTTP_ACCEPT' => 'application/json']);
        $first->assertStatus(200);
        $this->assertCount(1, $first->json('data.gallery_images'));

        // Retry après réponse perdue : même contenu, autre nom de fichier
        $retry = $this->call('PUT', "/api/admin/press-reviews/{$review->id}", [], [], [
            'gallery_images' => [$this->png(40)],
        ], ['HTTP_ACCEPT' => 'application/json']);
        $retry->assertStatus(200)->assertJsonStructure(['success', 'message', 'data' => ['id', 'gallery_images']]);
        $this->assertCount(1, $retry->json('data.gallery_images'));
        $this->assertCount(1, PressReview::find($review->id)->gallery_images);
        $this->assertCount($before + 1, glob(public_path('press-reviews/gallery/*')) ?: [], 'pas de doublon disque');
    }

    public function test_b5_two_different_files_give_two_images_and_same_batch_is_deduped(): void
    {
        $this->admin();
        $review = PressReview::create($this->reviewPayload(['slug' => 'cahier-test-2']));

        $response = $this->call('PUT', "/api/admin/press-reviews/{$review->id}", [], [], [
            'gallery_images' => [$this->png(40), $this->png(90), $this->png(90)],
        ], ['HTTP_ACCEPT' => 'application/json']);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.gallery_images'));
        foreach ($response->json('data.gallery_images') as $path) {
            $this->assertFileExists(public_path($path));
        }
    }

    public function test_b5_store_dedupes_within_request(): void
    {
        $this->admin();
        $response = $this->call('POST', '/api/admin/press-reviews', $this->reviewPayload(), [], [
            'gallery_images' => [$this->png(40), $this->png(40), $this->png(120)],
        ], ['HTTP_ACCEPT' => 'application/json']);

        $response->assertStatus(201)->assertJsonPath('data.id', PressReview::first()->id);
        $this->assertCount(2, $response->json('data.gallery_images'));
    }

    public function test_b5_existing_gallery_images_removal_deletes_removed_files(): void
    {
        $this->admin();
        $review = PressReview::create($this->reviewPayload(['slug' => 'cahier-test-3']));
        $created = $this->call('PUT', "/api/admin/press-reviews/{$review->id}", [], [], [
            'gallery_images' => [$this->png(40), $this->png(90)],
        ], ['HTTP_ACCEPT' => 'application/json']);
        [$keep, $drop] = $created->json('data.gallery_images');

        $response = $this->call('PUT', "/api/admin/press-reviews/{$review->id}", [
            'existing_gallery_images' => json_encode([$keep, 'press-reviews/gallery/../../.env']),
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $response->assertStatus(200);
        $this->assertSame([$keep], $response->json('data.gallery_images'));
        $this->assertFileExists(public_path($keep));
        $this->assertFileDoesNotExist(public_path($drop));
    }

    public function test_b5_malformed_keep_list_deletes_nothing_and_empty_list_removes_all(): void
    {
        $this->admin();
        $review = PressReview::create($this->reviewPayload(['slug' => 'cahier-test-5']));
        $created = $this->call('PUT', "/api/admin/press-reviews/{$review->id}", [], [], [
            'gallery_images' => [$this->png(41)],
        ], ['HTTP_ACCEPT' => 'application/json']);
        [$path] = $created->json('data.gallery_images');

        $bad = $this->call('PUT', "/api/admin/press-reviews/{$review->id}", [
            'existing_gallery_images' => '{pas du json',
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $bad->assertStatus(200);
        $this->assertSame([$path], $bad->json('data.gallery_images'));
        $this->assertFileExists(public_path($path));

        $empty = $this->call('PUT', "/api/admin/press-reviews/{$review->id}", [
            'existing_gallery_images' => '[]',
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $empty->assertStatus(200);
        $this->assertSame([], $empty->json('data.gallery_images'));
        $this->assertFileDoesNotExist(public_path($path));
    }

    public function test_b5_update_without_gallery_keys_leaves_gallery_untouched(): void
    {
        $this->admin();
        $review = PressReview::create($this->reviewPayload(['slug' => 'cahier-test-4']));
        $this->call('PUT', "/api/admin/press-reviews/{$review->id}", [], [], [
            'gallery_images' => [$this->png(40)],
        ], ['HTTP_ACCEPT' => 'application/json']);

        $response = $this->call('PUT', "/api/admin/press-reviews/{$review->id}", ['title' => 'Nouveau titre'], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.gallery_images'));
    }

    // ============ B6 : id présent dans toutes les réponses de sauvegarde ============

    public function test_b6_save_endpoints_return_object_with_id(): void
    {
        $this->admin();
        $cat = Categorie::factory()->create();
        $dom = Domaine::factory()->create();

        $this->postJson('/api/saveDomaine', ['name' => 'Dom'])->assertStatus(200)->assertJsonStructure(['domaine' => ['id']]);
        $this->postJson('/api/saveCategorie', ['name' => 'Cat', 'type' => 'book'])->assertStatus(200)->assertJsonStructure(['categorie' => ['id']]);
        $this->postJson('/api/saveService', ['title' => 'Srv', 'description' => 'd'])->assertStatus(200)->assertJsonStructure(['service' => ['id']]);
        $this->postJson('/api/saveVideo', [
            'title' => 'Vid', 'description' => 'd', 'video_url' => 'https://example.com/v',
            'thumbnail' => $this->png(30),
        ])->assertStatus(200)->assertJsonStructure(['video' => ['id']]);
        $this->postJson('/api/saveArticle', ['title' => 'Art', 'excerpt' => 'e', 'content' => 'c'])->assertStatus(200)->assertJsonStructure(['article' => ['id']]);
        $this->postJson('/api/saveMemoir', [
            'title' => 'Mem', 'author' => 'a', 'university' => 'u', 'domain' => $dom->id, 'year' => 2020, 'abstract' => 'x',
        ])->assertStatus(200)->assertJsonStructure(['memoir' => ['id']]);
        $this->postJson('/api/saveBook', [
            'title' => 'Bk', 'author' => 'a', 'category' => $cat->id, 'description' => 'd', 'price' => 10,
            'isbn' => 'ISBN-B6', 'pages' => 10,
            'document' => UploadedFile::fake()->create('l.pdf', 10, 'application/pdf'),
            'extraitdocument' => UploadedFile::fake()->create('e.pdf', 10, 'application/pdf'),
        ])->assertStatus(200)->assertJsonStructure(['book' => ['id']]);
        $this->postJson('/api/team_members', ['nom' => 'N', 'prenom' => 'P', 'role' => 'R'])->assertStatus(200)->assertJsonStructure(['team_member' => ['id']]);
        $this->postJson('/api/partners', ['nom' => 'N'])->assertStatus(200)->assertJsonStructure(['partner' => ['id']]);
        $this->call('POST', '/api/admin/press-reviews', $this->reviewPayload(), [], [], ['HTTP_ACCEPT' => 'application/json'])
            ->assertStatus(201)->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseCount('videos', 1);
        $this->createdFiles[] = public_path('video/' . \App\Models\Video::first()->thumbnail);
    }

    public function test_b6_memoir_extension_is_persisted(): void
    {
        $this->admin();
        $dom = Domaine::factory()->create();

        $response = $this->postJson('/api/saveMemoir', [
            'title' => 'Mem ext', 'author' => 'a', 'university' => 'u', 'domain' => $dom->id, 'year' => 2020, 'abstract' => 'x',
            'document' => UploadedFile::fake()->create('m.pdf', 10, 'application/pdf'),
        ]);

        $response->assertStatus(200);
        $memoir = Memoir::find($response->json('memoir.id'));
        $this->createdFiles[] = storage_path('app/private/memoirs/documents/' . $memoir->document);
        $this->assertSame('pdf', $memoir->extension);
    }

    // ============ B7 : échec disque => JSON géré ============

    public function test_b7_unreadable_file_returns_managed_json_error(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestIncomplete('chmod 000 sans effet sous root : à exécuter en utilisateur non privilégié.');
        }
        $this->admin();
        $path = $this->storePrivateMemoirFile('locked.pdf');
        $memoir = Memoir::factory()->create(['status' => 1, 'document' => 'locked.pdf']);
        chmod($path, 0000);

        $public = $this->get("/api/downloadmemoir/{$memoir->id}");
        $admin = $this->get("/api/admin/memoirs/{$memoir->id}/download");
        chmod($path, 0640);

        $public->assertStatus(500)->assertJson(['success' => false])->assertJsonStructure(['message']);
        $admin->assertStatus(500)->assertJson(['success' => false])->assertJsonStructure(['message']);
    }
}
