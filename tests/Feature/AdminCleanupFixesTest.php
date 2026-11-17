<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\PressReviewController;
use App\Models\Article;
use App\Models\Book;
use App\Models\Categorie;
use App\Models\Domaine;
use App\Models\Memoir;
use App\Models\Partners;
use App\Models\PressReview;
use App\Models\Service;
use App\Models\Team_members;
use App\Models\User;
use App\Models\Video;
use App\Services\GalleryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Correctifs de nettoyage back-office (items 1..10).
 * Base de test réelle (sqlite en mémoire) et vrai disque : aucune couche mockée.
 */
class AdminCleanupFixesTest extends TestCase
{
    use RefreshDatabase;

    private const MISSING_ID = 987654;

    private array $teamBase = ['nom' => 'N', 'prenom' => 'P', 'role' => 'R'];

    /** @var string[] */
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
            File::delete(public_path((string) $review->cover_image));
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

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('doc.pdf', 5, 'application/pdf');
    }

    private function putFile(string $absoluteDir, string $name, string $content = 'x'): string
    {
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0775, true);
        }
        $path = $absoluteDir . '/' . $name;
        file_put_contents($path, $content);
        $this->createdFiles[] = $path;

        return $path;
    }

    private function putPublic(string $dir, string $name): string
    {
        return $this->putFile(public_path($dir), $name);
    }

    private function putPrivate(string $dir, string $name, string $content = '%PDF-1.4 x'): string
    {
        return $this->putFile(storage_path('app/private/' . $dir), $name, $content);
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

    private function putReview(int $id, array $params = [], array $files = [])
    {
        return $this->call('PUT', "/api/admin/press-reviews/{$id}", $params, [], $files, ['HTTP_ACCEPT' => 'application/json']);
    }

    // ============ 1 : id inconnu => 404, jamais de création ============

    public function test_1_unknown_id_returns_404_and_creates_nothing(): void
    {
        $this->admin();
        $missing = ['id' => self::MISSING_ID];

        $this->postJson('/api/saveDomaine', $missing + ['name' => 'Fantôme'])
            ->assertStatus(404)->assertJson(['success' => false]);
        $this->postJson('/api/saveCategorie', $missing + ['name' => 'Fantôme'])
            ->assertStatus(404)->assertJson(['success' => false]);
        $this->postJson('/api/saveService', $missing + ['title' => 'Fantôme'])
            ->assertStatus(404)->assertJson(['success' => false]);
        $this->postJson('/api/saveVideo', $missing + ['title' => 'Fantôme'])
            ->assertStatus(404)->assertJson(['success' => false]);

        $this->assertSame(0, Domaine::count());
        $this->assertSame(0, Categorie::count());
        $this->assertSame(0, Service::count());
        $this->assertSame(0, Video::count());
    }

    public function test_1_unknown_id_on_book_memoir_article_is_404_without_orphan_upload(): void
    {
        $this->admin();
        $before = count(glob(public_path('articles/*')) ?: []);

        $this->post('/api/saveArticle', ['id' => self::MISSING_ID, 'image' => $this->png(33)], ['Accept' => 'application/json'])
            ->assertStatus(404)->assertJson(['success' => false]);
        $this->postJson('/api/saveBook', ['id' => self::MISSING_ID, 'title' => 'X'])
            ->assertStatus(404)->assertJson(['success' => false]);
        $this->postJson('/api/saveMemoir', ['id' => self::MISSING_ID, 'title' => 'X'])
            ->assertStatus(404)->assertJson(['success' => false]);

        $this->assertCount($before, glob(public_path('articles/*')) ?: [], 'upload orphelin sur id inconnu');
    }

    public function test_1_domaine_and_categorie_update_existing_and_validate(): void
    {
        $this->admin();
        $domaine = Domaine::factory()->create(['name' => 'Histoire', 'description' => 'Desc']);

        $this->postJson('/api/saveDomaine', ['id' => $domaine->id, 'is_active' => false])
            ->assertStatus(200)->assertJsonPath('domaine.name', 'Histoire');
        $fresh = Domaine::find($domaine->id);
        $this->assertSame('Histoire', $fresh->name);
        $this->assertSame('Desc', $fresh->description);
        $this->assertEquals(0, $fresh->is_active);
        $this->assertSame(1, Domaine::count());

        $this->postJson('/api/saveDomaine', ['description' => 'sans nom'])
            ->assertStatus(422)->assertJsonPath('success', false)->assertJsonStructure(['errors' => ['name']]);
        $this->postJson('/api/saveDomaine', ['name' => str_repeat('a', 256)])->assertStatus(422);
        $this->postJson('/api/saveDomaine', ['name' => 'Ok', 'is_active' => 'peut-etre'])->assertStatus(422);
        $this->postJson('/api/saveDomaine', ['id' => $domaine->id, 'name' => ''])->assertStatus(422);

        $this->postJson('/api/saveDomaine', ['name' => 'Nouveau'])
            ->assertStatus(200)->assertJsonPath('domaine.name', 'Nouveau');

        $categorie = Categorie::factory()->create(['name' => 'Romans']);
        $this->postJson('/api/saveCategorie', ['id' => $categorie->id, 'description' => 'D'])
            ->assertStatus(200);
        $this->assertSame('Romans', Categorie::find($categorie->id)->name);
        $this->postJson('/api/saveCategorie', ['type' => 'book'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['name']]);
        $this->postJson('/api/saveCategorie', ['name' => 'Poésie', 'type' => 'book'])->assertStatus(200);
        $this->assertSame(2, Categorie::count());
    }

    // ============ 2 : JSON double encodé ============

    public function test_2_memoir_keywords_are_stored_once_encoded_and_returned_as_array(): void
    {
        $this->admin();
        $domaine = Domaine::factory()->create();

        $response = $this->postJson('/api/saveMemoir', [
            'title' => 'Mémoire JSON', 'author' => 'A', 'university' => 'U', 'domain' => $domaine->id,
            'year' => 2020, 'abstract' => 'Résumé', 'keywords' => ['alpha', 'béta'],
        ]);

        $response->assertStatus(200)->assertJsonPath('memoir.keywords', ['alpha', 'béta']);
        $raw = DB::table('memoirs')->where('id', $response->json('memoir.id'))->value('keywords');
        $this->assertSame(['alpha', 'béta'], json_decode($raw, true), 'un seul niveau d\'encodage');
    }

    public function test_2_partial_memoir_update_keeps_keywords(): void
    {
        $this->admin();
        $memoir = Memoir::factory()->create(['keywords' => ['a', 'b']]);

        $this->postJson('/api/saveMemoir', ['id' => $memoir->id, 'title' => 'Nouveau titre'])->assertStatus(200);

        $this->assertSame(['a', 'b'], Memoir::find($memoir->id)->keywords);
    }

    public function test_2_legacy_double_encoded_memoir_is_read_as_array(): void
    {
        $memoir = Memoir::factory()->create(['status' => 1]);
        DB::table('memoirs')->where('id', $memoir->id)->update(['keywords' => json_encode(json_encode(['x', 'y']))]);

        $this->getJson("/api/memoirs/{$memoir->id}")
            ->assertStatus(200)->assertJsonPath('keywords', ['x', 'y']);
    }

    public function test_2_team_social_links_single_encoded_and_legacy_read(): void
    {
        $this->admin();
        $links = [['name' => 'x', 'url' => 'https://x.test']];

        $created = $this->postJson('/api/team_members', [
            'nom' => 'Doe', 'prenom' => 'Jane', 'role' => 'Éditrice', 'social_links' => $links,
        ]);
        $created->assertStatus(200);
        $raw = DB::table('team_members')->where('id', $created->json('team_member.id'))->value('social_links');
        $this->assertSame($links, json_decode($raw, true));

        DB::table('team_members')->where('id', $created->json('team_member.id'))
            ->update(['social_links' => json_encode(json_encode($links))]);
        $list = $this->getJson('/api/team_members');
        $list->assertStatus(200)->assertJsonPath('team_members.0.social_links', $links);
    }

    public function test_2_fix_command_dry_run_apply_and_idempotent(): void
    {
        $memoir = Memoir::factory()->create(['keywords' => ['ok']]);
        $double = Memoir::factory()->create();
        $member = Team_members::create(['nom' => 'N', 'prenom' => 'P', 'role' => 'R']);
        $encoded = json_encode(json_encode(['k1', 'k2']));
        DB::table('memoirs')->where('id', $double->id)->update(['keywords' => $encoded]);
        DB::table('team_members')->where('id', $member->id)->update(['social_links' => json_encode(json_encode([['url' => 'u']]))]);
        $okRaw = DB::table('memoirs')->where('id', $memoir->id)->value('keywords');

        $this->artisan('app:fix-double-encoded-json')->assertExitCode(0);
        $this->assertSame($encoded, DB::table('memoirs')->where('id', $double->id)->value('keywords'), 'dry-run n\'écrit pas');

        $this->artisan('app:fix-double-encoded-json', ['--apply' => true])->assertExitCode(0);
        $this->assertSame(['k1', 'k2'], json_decode(DB::table('memoirs')->where('id', $double->id)->value('keywords'), true));
        $this->assertSame([['url' => 'u']], json_decode(DB::table('team_members')->where('id', $member->id)->value('social_links'), true));
        $this->assertSame($okRaw, DB::table('memoirs')->where('id', $memoir->id)->value('keywords'), 'ligne saine inchangée');

        $after = DB::table('memoirs')->where('id', $double->id)->value('keywords');
        $this->artisan('app:fix-double-encoded-json', ['--apply' => true])->assertExitCode(0);
        $this->assertSame($after, DB::table('memoirs')->where('id', $double->id)->value('keywords'), 'idempotent');
    }

    // ============ 3 : is_active ============

    public function test_3_team_member_is_active_partial_update_and_string_booleans(): void
    {
        $this->admin();
        $member = Team_members::create(['nom' => 'N', 'prenom' => 'P', 'role' => 'R', 'is_active' => false]);

        $this->postJson("/api/team_members/{$member->id}", $this->teamBase)->assertStatus(200);
        $this->assertFalse((bool) Team_members::find($member->id)->is_active, 'absent = valeur conservée');

        $this->postJson("/api/team_members/{$member->id}", $this->teamBase + ['is_active' => 'true'])->assertStatus(200);
        $this->assertTrue((bool) Team_members::find($member->id)->is_active);

        $this->postJson("/api/team_members/{$member->id}", $this->teamBase)->assertStatus(200);
        $this->assertTrue((bool) Team_members::find($member->id)->is_active, 'mise à jour partielle conserve actif');

        $this->postJson("/api/team_members/{$member->id}", $this->teamBase + ['is_active' => '0'])->assertStatus(200);
        $this->assertFalse((bool) Team_members::find($member->id)->is_active);

        Team_members::where('id', $member->id)->update(['is_active' => true]);
        $this->postJson("/api/team_members/{$member->id}", $this->teamBase + ['is_active' => 'false'])->assertStatus(200);
        $this->assertFalse((bool) Team_members::find($member->id)->is_active);

        $this->postJson("/api/team_members/{$member->id}", $this->teamBase + ['is_active' => 'banane'])->assertStatus(422);
    }

    public function test_3_partner_is_active_and_creation_default(): void
    {
        $this->admin();
        $created = $this->postJson('/api/partners', ['nom' => 'Acme']);
        $created->assertStatus(200)->assertJsonPath('partner.is_active', true);
        $id = $created->json('partner.id');

        $this->postJson("/api/partners/{$id}", ['nom' => 'Acme 2'])->assertStatus(200);
        $this->assertTrue((bool) Partners::find($id)->is_active);

        $this->postJson("/api/partners/{$id}", ['nom' => 'Acme', 'is_active' => '0'])->assertStatus(200);
        $this->assertFalse((bool) Partners::find($id)->is_active);

        $this->postJson("/api/partners/{$id}", ['nom' => 'Acme 3'])->assertStatus(200);
        $this->assertFalse((bool) Partners::find($id)->is_active, 'absent = valeur conservée');

        $this->postJson("/api/partners/{$id}", ['nom' => 'Acme', 'is_active' => true])->assertStatus(200);
        $this->postJson("/api/partners/{$id}", ['nom' => 'Acme', 'is_active' => 'false'])->assertStatus(200);
        $this->assertFalse((bool) Partners::find($id)->is_active);
    }

    // ============ 4 : téléchargements ============

    public function test_4_public_memoir_download_uses_safe_name_and_real_extension(): void
    {
        $this->putPrivate('memoirs/documents', 'pubmem.docx', 'docx');
        $memoir = Memoir::factory()->create([
            'status' => 1,
            'title' => "Évil\"/../x\r\nSet-Cookie: a=b " . str_repeat('z', 300),
            'document' => 'pubmem.docx',
            'extension' => 'pdf',
            'downloads_count' => 0,
        ]);

        $response = $this->get("/api/downloadmemoir/{$memoir->id}");

        $response->assertStatus(200);
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment', $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertStringNotContainsString('../', $disposition);
        $this->assertMatchesRegularExpression('/filename="[^"]*\.docx"/', $disposition);
        $this->assertLessThanOrEqual(150 + strlen('.docx'), strlen($this->filenameOf($disposition)));
        $this->assertSame(1, (int) Memoir::find($memoir->id)->downloads_count);
    }

    public function test_4_public_book_download_extension_from_stored_file(): void
    {
        $this->putPrivate('books/extrait_documents', 'extr.pdf');
        $book = Book::factory()->create([
            'title' => 'Titre "piégé"/\\ avec ; point-virgule',
            'extraitdocument' => 'books/extrait_documents/extr.pdf',
            'document' => 'books/documents/full.docx',
        ]);

        $response = $this->get("/api/downloadbook/{$book->id}");

        $response->assertStatus(200);
        $name = $this->filenameOf((string) $response->headers->get('Content-Disposition'));
        $this->assertMatchesRegularExpression('/^[\p{L}\p{N} ._-]+\.pdf$/u', $name);
    }

    private function filenameOf(string $disposition): string
    {
        preg_match('/filename="([^"]*)"/', $disposition, $m);

        return $m[1] ?? '';
    }

    // ============ 5 : format d'erreur auth ============

    public function test_5_controller_guard_formats_non_admin_and_guest(): void
    {
        $user = User::factory()->create(['role' => 0]);
        Passport::actingAs($user);

        $forbidden = app(AdminController::class)->getbook();
        $this->assertSame(403, $forbidden->getStatusCode());
        $this->assertSame(['success' => false, 'message' => 'Accès refusé'], $forbidden->getData(true));

        $adminIndex = app(PressReviewController::class)->adminIndex(request());
        $this->assertSame(403, $adminIndex->getStatusCode());
        $this->assertFalse($adminIndex->getData(true)['success']);

        auth()->forgetGuards();
        app('auth')->guard('api')->forgetUser();
        app('auth')->shouldUse('api');
        $guest = app(AdminController::class)->getarticle();
        $this->assertSame(401, $guest->getStatusCode());
        $this->assertSame(['success' => false, 'message' => 'Non authentifié.'], $guest->getData(true));
    }

    // ============ 6 : casse des routes / Handler ============

    public function test_6_routes_use_capitalised_route_facade(): void
    {
        $this->assertDoesNotMatchRegularExpression('/\broute::/', (string) file_get_contents(base_path('routes/api.php')));
    }

    public function test_6_handler_constants_precede_methods(): void
    {
        $source = (string) file_get_contents(app_path('Exceptions/Handler.php'));
        $this->assertLessThan(strpos($source, 'public function register'), strpos($source, 'const MESSAGE_NOT_FOUND'));
        $this->assertLessThan(strpos($source, 'public function register'), strpos($source, 'const MESSAGE_UNAUTHENTICATED'));
    }

    // ============ 7 : orphelins de fichiers ============

    public function test_7_article_image_replaced_deletes_old_file_only_after_success(): void
    {
        $this->admin();
        $old = $this->putPublic('articles', 'old-article.png');
        $article = Article::factory()->create(['image' => 'old-article.png']);

        // Échec de validation : l'ancien fichier est conservé.
        $this->post('/api/saveArticle', ['id' => $article->id, 'title' => str_repeat('t', 300), 'image' => $this->png(50)], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->assertFileExists($old);
        $this->assertSame('old-article.png', Article::find($article->id)->image);

        $response = $this->post('/api/saveArticle', ['id' => $article->id, 'image' => $this->png(60)], ['Accept' => 'application/json']);
        $response->assertStatus(200);
        $new = Article::find($article->id)->image;
        $this->assertNotSame('old-article.png', $new);
        $this->assertFileExists(public_path('articles/' . $new));
        $this->createdFiles[] = public_path('articles/' . $new);
        $this->assertFileDoesNotExist($old);
    }

    public function test_7_external_empty_and_traversal_old_values_are_never_deleted(): void
    {
        $this->admin();
        $outside = $this->putPublic('', 'keepme.txt');
        $article = Article::factory()->create(['image' => '../keepme.txt']);

        $this->post('/api/saveArticle', ['id' => $article->id, 'image' => $this->png(61)], ['Accept' => 'application/json'])->assertStatus(200);
        $this->createdFiles[] = public_path('articles/' . Article::find($article->id)->image);
        $this->assertFileExists($outside, 'basename + répertoire attendu uniquement');

        $external = Article::factory()->create(['image' => 'https://cdn.example.com/a.png']);
        $this->post('/api/saveArticle', ['id' => $external->id, 'image' => $this->png(62)], ['Accept' => 'application/json'])->assertStatus(200);
        $this->createdFiles[] = public_path('articles/' . Article::find($external->id)->image);

        $empty = Article::factory()->create(['image' => null]);
        $this->post('/api/saveArticle', ['id' => $empty->id, 'image' => $this->png(63)], ['Accept' => 'application/json'])->assertStatus(200);
        $this->createdFiles[] = public_path('articles/' . Article::find($empty->id)->image);
    }

    public function test_7_book_cover_and_private_documents_replaced(): void
    {
        $this->admin();
        $cover = $this->putPublic('books/covers', 'oldcover.png');
        $doc = $this->putPrivate('books/documents', 'olddoc.pdf');
        $extrait = $this->putPrivate('books/extrait_documents', 'oldextr.pdf');
        $book = Book::factory()->create([
            'cover' => 'oldcover.png', 'document' => 'olddoc.pdf', 'extraitdocument' => 'oldextr.pdf',
        ]);

        $this->post('/api/saveBook', [
            'id' => $book->id, 'cover' => $this->png(70), 'document' => $this->pdf(), 'extraitdocument' => $this->pdf(),
        ], ['Accept' => 'application/json'])->assertStatus(200);

        $fresh = Book::find($book->id);
        $this->createdFiles[] = public_path('books/covers/' . $fresh->cover);
        $this->createdFiles[] = storage_path('app/private/books/documents/' . $fresh->document);
        $this->createdFiles[] = storage_path('app/private/books/extrait_documents/' . $fresh->extraitdocument);
        $this->assertFileDoesNotExist($cover);
        $this->assertFileDoesNotExist($doc);
        $this->assertFileDoesNotExist($extrait);
        $this->assertFileExists(storage_path('app/private/books/documents/' . $fresh->document));
    }

    public function test_7_memoir_video_partner_team_and_press_cover_replaced(): void
    {
        $this->admin();

        $mCover = $this->putPublic('memoirs/covers', 'mold.png');
        $mDoc = $this->putPrivate('memoirs/documents', 'mold.pdf');
        $memoir = Memoir::factory()->create(['cover' => 'mold.png', 'document' => 'mold.pdf']);
        $this->post('/api/saveMemoir', ['id' => $memoir->id, 'cover' => $this->png(71), 'document' => $this->pdf()], ['Accept' => 'application/json'])->assertStatus(200);
        $m = Memoir::find($memoir->id);
        $this->createdFiles[] = public_path('memoirs/covers/' . $m->cover);
        $this->createdFiles[] = storage_path('app/private/memoirs/documents/' . $m->document);
        $this->assertFileDoesNotExist($mCover);
        $this->assertFileDoesNotExist($mDoc);

        $vOld = $this->putPublic('video', 'vold.png');
        $video = Video::create(['title' => 'V', 'description' => 'D', 'video_url' => 'https://v.test/1', 'thumbnail' => 'vold.png', 'status' => 1]);
        $this->post('/api/saveVideo', ['id' => $video->id, 'thumbnail' => $this->png(72)], ['Accept' => 'application/json'])->assertStatus(200);
        $this->createdFiles[] = public_path('video/' . Video::find($video->id)->thumbnail);
        $this->assertFileDoesNotExist($vOld);

        $pOld = $this->putPublic('partners', 'pold.png');
        $partner = Partners::create(['nom' => 'P', 'image' => 'pold.png']);
        $this->post("/api/partners/{$partner->id}", ['nom' => 'P', 'image' => $this->png(73)], ['Accept' => 'application/json'])->assertStatus(200);
        $this->createdFiles[] = public_path('partners/' . Partners::find($partner->id)->image);
        $this->assertFileDoesNotExist($pOld);

        $tOld = $this->putPublic('team', 'told.png');
        $member = Team_members::create(['nom' => 'N', 'prenom' => 'P', 'role' => 'R', 'photo' => 'told.png']);
        $this->post("/api/team_members/{$member->id}", $this->teamBase + ['photo' => $this->png(74)], ['Accept' => 'application/json'])->assertStatus(200);
        $this->createdFiles[] = public_path('team/' . Team_members::find($member->id)->photo);
        $this->assertFileDoesNotExist($tOld);

        $rOld = $this->putPublic('press-reviews/covers', 'rold.png');
        $review = PressReview::create($this->reviewPayload(['slug' => 'cover-test', 'cover_image' => 'press-reviews/covers/rold.png']));
        $this->putReview($review->id, [], ['cover_image' => $this->png(75)])->assertStatus(200);
        $this->assertFileDoesNotExist($rOld);
        $this->assertFileExists(public_path(PressReview::find($review->id)->cover_image));
    }

    public function test_7_partner_validation_failure_keeps_old_image(): void
    {
        $this->admin();
        $old = $this->putPublic('partners', 'keep.png');
        $partner = Partners::create(['nom' => 'P', 'image' => 'keep.png']);

        $this->post("/api/partners/{$partner->id}", ['nom' => 'P', 'image' => UploadedFile::fake()->create('x.txt', 3, 'text/plain')], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertFileExists($old);
        $this->assertSame('keep.png', Partners::find($partner->id)->image);
    }

    // ============ 8 : galerie ============

    public function test_8_single_file_gallery_on_update_is_normalised_and_validated(): void
    {
        $this->admin();
        $review = PressReview::create($this->reviewPayload(['slug' => 'single-gallery']));

        $ok = $this->putReview($review->id, [], ['gallery_images' => $this->png(80)]);
        $ok->assertStatus(200);
        $this->assertCount(1, $ok->json('data.gallery_images'));
        $this->assertFileExists(public_path($ok->json('data.gallery_images.0')));

        $bad = $this->putReview($review->id, [], ['gallery_images' => UploadedFile::fake()->create('evil.php', 2, 'text/x-php')]);
        $bad->assertStatus(422);
        $this->assertCount(1, PressReview::find($review->id)->gallery_images);
    }

    public function test_8_store_with_single_non_image_gallery_file_is_rejected(): void
    {
        $this->admin();

        $this->call('POST', '/api/admin/press-reviews', $this->reviewPayload(), [], [
            'gallery_images' => UploadedFile::fake()->create('evil.php', 2, 'text/x-php'),
        ], ['HTTP_ACCEPT' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, PressReview::count());
    }

    public function test_8_move_failure_mid_batch_cleans_moved_files_and_returns_generic_500(): void
    {
        $this->admin();
        $review = PressReview::create($this->reviewPayload(['slug' => 'fail-gallery']));
        $before = glob(public_path('press-reviews/gallery/*')) ?: [];

        $this->app->bind(GalleryService::class, fn ($app) => new class($app->make(\App\Services\StoredFileService::class)) extends GalleryService {
            private int $calls = 0;

            protected function moveUpload(UploadedFile $file, string $directory, string $filename): void
            {
                if (++$this->calls === 2) {
                    throw new \RuntimeException('disk full at /var/www/secret/path');
                }
                parent::moveUpload($file, $directory, $filename);
            }
        });

        $response = $this->putReview($review->id, [], ['gallery_images' => [$this->png(81), $this->png(82), $this->png(83)]]);

        $response->assertStatus(500)->assertJsonPath('success', false);
        $this->assertStringNotContainsString('/var/www', (string) $response->getContent());
        $this->assertStringNotContainsString(public_path(), (string) $response->getContent());
        $this->assertSame($before, glob(public_path('press-reviews/gallery/*')) ?: [], 'fichiers déjà déplacés nettoyés');
        $this->assertEmpty(PressReview::find($review->id)->gallery_images ?? []);
    }

    // ============ 10 : DELETE d'une image de galerie ============

    private function reviewWithGallery(string $slug, int ...$shades): array
    {
        $review = PressReview::create($this->reviewPayload(['slug' => $slug]));
        $files = [];
        foreach ($shades as $shade) {
            $files[] = $this->png($shade);
        }
        $paths = $this->putReview($review->id, [], ['gallery_images' => $files])->json('data.gallery_images');

        return [$review, $paths];
    }

    public function test_10_delete_gallery_image_removes_reference_and_file(): void
    {
        $this->admin();
        [$review, $paths] = $this->reviewWithGallery('del-gallery', 90, 91);
        [$keep, $drop] = $paths;

        $response = $this->deleteJson("/api/admin/press-reviews/{$review->id}/gallery/" . basename($drop));

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertSame([$keep], PressReview::find($review->id)->gallery_images);
        $this->assertFileExists(public_path($keep));
        $this->assertFileDoesNotExist(public_path($drop));
    }

    public function test_10_delete_gallery_image_404_when_not_in_this_review_or_traversal(): void
    {
        $this->admin();
        [$reviewA, $pathsA] = $this->reviewWithGallery('gal-a', 92);
        [$reviewB] = $this->reviewWithGallery('gal-b', 93);
        $secret = $this->putPublic('press-reviews/gallery', 'not-in-any-review.png');

        $this->deleteJson("/api/admin/press-reviews/{$reviewB->id}/gallery/" . basename($pathsA[0]))
            ->assertStatus(404)->assertJsonPath('success', false);
        $this->assertFileExists(public_path($pathsA[0]), 'image d\'une autre revue intacte');

        $this->deleteJson("/api/admin/press-reviews/{$reviewB->id}/gallery/not-in-any-review.png")->assertStatus(404);
        $this->assertFileExists($secret);

        $this->deleteJson("/api/admin/press-reviews/{$reviewB->id}/gallery/..%2F..%2Findex.php")->assertStatus(404);
        $this->deleteJson('/api/admin/press-reviews/' . self::MISSING_ID . '/gallery/x.png')->assertStatus(404);
    }

    public function test_10_delete_gallery_image_requires_admin(): void
    {
        $this->deleteJson('/api/admin/press-reviews/1/gallery/x.png')->assertStatus(401);

        Passport::actingAs(User::factory()->create(['role' => 0]));
        $this->deleteJson('/api/admin/press-reviews/1/gallery/x.png')->assertStatus(403);
    }
}
