<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Memoir;
use App\Models\Partners;
use App\Models\PressReview;
use App\Models\User;
use App\Services\ImageVariantService;
use App\Services\ImageVariantResult;
use App\Models\Domaine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Passport;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

/**
 * Variantes à l'upload. public_path() est redirigé vers un répertoire temporaire ;
 * la garde de listing vérifie en plus que le vrai public/ n'est pas touché.
 */
class ImageVariantUploadTest extends TestCase
{
    use ImageFixtures;
    use RefreshDatabase;

    private string $public;
    /** @var array<string, string[]> */
    private array $realListing = [];

    private const WATCHED = ['articles', 'books/covers', 'memoirs/covers', 'video', 'partners', 'team', 'press-reviews/covers', 'press-reviews/gallery', 'press-reviews/editor', 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::WATCHED as $d) {
            $this->realListing[$d] = $this->listing(base_path('public/' . $d));
        }
        $this->public = sys_get_temp_dir() . '/pub-' . bin2hex(random_bytes(4));
        mkdir($this->public, 0775, true);
        $this->app->usePublicPath($this->public);
        config(['images.base_path' => null, 'images.directories' => self::WATCHED]);
        Passport::actingAs(User::factory()->create(['role' => 1]));
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->public);
        foreach (self::WATCHED as $d) {
            $this->assertSame($this->realListing[$d], $this->listing(base_path('public/' . $d)), "public/{$d} modifié par les tests");
        }
        parent::tearDown();
    }

    private function upload(string $name = 'cover.png'): UploadedFile
    {
        $tmp = $this->makePng(sys_get_temp_dir() . '/up-' . bin2hex(random_bytes(4)) . '.png', 500, 400);
        $file = UploadedFile::fake()->createWithContent($name, (string) file_get_contents($tmp));
        @unlink($tmp);

        return $file;
    }

    private function article(array $extra = [])
    {
        return $this->post('/api/saveArticle', array_merge(['title' => 'T', 'excerpt' => 'E', 'content' => 'C', 'image' => $this->upload()], $extra), ['Accept' => 'application/json']);
    }

    public function test_article_cover_upload_creates_variant(): void
    {
        $this->article()->assertStatus(200);

        $image = Article::firstOrFail()->image;
        $this->assertFileExists($this->public . '/articles/' . $image);
        $this->assertFileExists($this->public . '/articles/' . $image . '.webp');
        $this->assertSame(0664, fileperms($this->public . '/articles/' . $image . '.webp') & 0777);
    }

    public function test_replacement_deletes_old_variant_and_creates_new(): void
    {
        $this->article()->assertStatus(200);
        $old = Article::firstOrFail()->image;
        $this->assertFileExists($this->public . '/articles/' . $old . '.webp');

        $this->article(['id' => Article::firstOrFail()->id])->assertStatus(200);

        $new = Article::firstOrFail()->image;
        $this->assertNotSame($old, $new);
        $this->assertFileDoesNotExist($this->public . '/articles/' . $old);
        $this->assertFileDoesNotExist($this->public . '/articles/' . $old . '.webp');
        $this->assertFileExists($this->public . '/articles/' . $new . '.webp');
    }

    public function test_memoir_delete_removes_cover_variant(): void
    {
        $domaine = Domaine::create(['name' => 'D']);
        $this->post('/api/saveMemoir', [
            'title' => 'M', 'author' => 'A', 'university' => 'U', 'domain' => $domaine->id, 'year' => 2020,
            'abstract' => 'x', 'cover' => $this->upload(),
        ], ['Accept' => 'application/json'])->assertStatus(200);
        $cover = Memoir::firstOrFail()->cover;
        $this->assertFileExists($this->public . '/memoirs/covers/' . $cover . '.webp');

        $this->deleteJson('/api/deleteMemoir/' . Memoir::firstOrFail()->id)->assertStatus(200);

        $this->assertFileDoesNotExist($this->public . '/memoirs/covers/' . $cover . '.webp');
    }

    public function test_partner_and_press_review_cover_and_gallery_get_variants(): void
    {
        $this->post('/api/partners', ['nom' => 'P', 'image' => $this->upload('logo.png')], ['Accept' => 'application/json'])->assertStatus(200);
        $logo = Partners::firstOrFail()->image;
        $this->assertFileExists($this->public . '/partners/' . $logo . '.webp');

        $this->call('POST', '/api/admin/press-reviews', [
            'title' => 'R', 'summary' => 's', 'content' => '<p>c</p>', 'category' => 'article', 'status' => 'draft',
        ], [], [
            'cover_image' => $this->upload(),
            'gallery_images' => [$this->upload('g.png')],
        ], ['HTTP_ACCEPT' => 'application/json'])->assertStatus(201);

        $review = PressReview::firstOrFail();
        $this->assertFileExists($this->public . '/' . $review->cover_image . '.webp');
        $this->assertFileExists($this->public . '/' . $review->gallery_images[0] . '.webp');
    }

    public function test_gd_failure_does_not_fail_the_request(): void
    {
        $this->app->bind(ImageVariantService::class, fn () => new class extends ImageVariantService {
            public function generateForStored(?string $stored, string $directory, bool $force = false): ImageVariantResult
            {
                throw new \RuntimeException('GD cassé');
            }
        });

        $this->article()->assertStatus(200);

        $image = Article::firstOrFail()->image;
        $this->assertFileExists($this->public . '/articles/' . $image);
        $this->assertFileDoesNotExist($this->public . '/articles/' . $image . '.webp');
    }

    public function test_variant_is_not_generated_when_database_write_fails(): void
    {
        $this->post('/api/saveArticle', ['id' => 987654, 'image' => $this->upload()], ['Accept' => 'application/json'])->assertStatus(404);

        $this->assertSame([], glob($this->public . '/articles/*') ?: []);
    }

    public function test_editor_image_upload_creates_variant(): void
    {
        $r = $this->post('/api/admin/press-reviews/upload-image', ['image' => $this->upload('e.png')], ['Accept' => 'application/json']);

        $r->assertStatus(200);
        $files = glob($this->public . '/press-reviews/editor/*') ?: [];
        $this->assertCount(2, $files);
        $this->assertSame(1, count(array_filter($files, fn ($f) => str_ends_with($f, '.png.webp'))));
    }

    public function test_service_image_upload_creates_variant_and_replacement_cleans_old(): void
    {
        $post = fn (array $extra = []) => $this->post('/api/saveService', array_merge(['title' => 'S', 'description' => 'D', 'image' => $this->upload('s.png')], $extra), ['Accept' => 'application/json']);
        $post()->assertStatus(200);
        $old = \App\Models\Service::firstOrFail()->image;
        $this->assertFileExists($this->public . '/service/' . $old . '.webp');

        $post(['id' => \App\Models\Service::firstOrFail()->id])->assertStatus(200);

        $new = \App\Models\Service::firstOrFail()->image;
        $this->assertFileDoesNotExist($this->public . '/service/' . $old . '.webp');
        $this->assertFileExists($this->public . '/service/' . $new . '.webp');
    }
}
