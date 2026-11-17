<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Categorie;
use App\Models\Domaine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = '3f1c1f6e-2f0b-4c63-9a53-0a0e2f6d8b11';
    private const HEADER = 'Idempotency-Key';

    /** @var array<int, string> fichiers à supprimer en tearDown */
    private array $beforeFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->beforeFiles = $this->articleFiles();
        config(['idempotency.lock_wait_seconds' => 0]);
    }

    protected function tearDown(): void
    {
        foreach (array_diff($this->articleFiles(), $this->beforeFiles) as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function articleFiles(): array
    {
        return glob(public_path('articles/*')) ?: [];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 1]);
        Passport::actingAs($user);

        return $user;
    }

    private function articlePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Mon article',
            'excerpt' => 'Résumé',
            'content' => 'Contenu',
        ], $overrides);
    }

    private function send(string $uri, array $data, ?string $key = self::KEY)
    {
        return $this->postJson($uri, $data, $key === null ? [] : [self::HEADER => $key]);
    }

    public function test_same_key_twice_creates_one_row_and_replays_same_response(): void
    {
        $this->admin();

        $first = $this->send('/api/saveArticle', $this->articlePayload());
        $second = $this->send('/api/saveArticle', $this->articlePayload());

        $first->assertStatus(200)->assertHeaderMissing('Idempotent-Replayed');
        $second->assertStatus(200)->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame(1, Article::count());
    }

    public function test_without_key_creates_two_rows(): void
    {
        $this->admin();

        $this->send('/api/saveArticle', $this->articlePayload(), null)->assertStatus(200);
        $this->send('/api/saveArticle', $this->articlePayload(), null)->assertStatus(200);

        $this->assertSame(2, Article::count());
    }

    public function test_invalid_key_returns_400(): void
    {
        $this->admin();

        foreach (['pas-un-uuid', str_repeat('a', 65)] as $bad) {
            $this->send('/api/saveArticle', $this->articlePayload(), $bad)
                ->assertStatus(400)
                ->assertJson(['success' => false]);
        }
        $this->assertSame(0, Article::count());
    }

    public function test_same_key_with_different_body_returns_422(): void
    {
        $this->admin();

        $this->send('/api/saveArticle', $this->articlePayload())->assertStatus(200);
        $this->send('/api/saveArticle', $this->articlePayload(['title' => 'Autre']))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(1, Article::count());
    }

    public function test_validation_failure_is_not_memoized_and_corrected_retry_creates(): void
    {
        $this->admin();

        $this->send('/api/saveArticle', $this->articlePayload(['title' => '']))->assertStatus(422);
        $this->assertSame(0, Article::count());

        $this->send('/api/saveArticle', $this->articlePayload())
            ->assertStatus(200)
            ->assertHeaderMissing('Idempotent-Replayed');
        $this->assertSame(1, Article::count());
    }

    public function test_two_users_with_same_key_do_not_replay_each_other(): void
    {
        $this->admin();
        $this->send('/api/saveArticle', $this->articlePayload())->assertStatus(200);

        $this->admin();
        $this->send('/api/saveArticle', $this->articlePayload())
            ->assertStatus(200)
            ->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(2, Article::count());
    }

    public function test_same_key_on_two_endpoints_is_independent(): void
    {
        $this->admin();

        $this->send('/api/saveDomaine', ['name' => 'Histoire'])->assertStatus(200);
        $this->send('/api/saveCategorie', ['name' => 'Roman', 'type' => 'livre'])
            ->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(1, Domaine::count());
        $this->assertSame(1, Categorie::count());
    }

    public function test_update_with_id_is_never_memoized(): void
    {
        $this->admin();
        $article = Article::factory()->create(['title' => 'Avant']);

        $this->send('/api/saveArticle', ['id' => $article->id, 'title' => 'V2'])->assertStatus(200);
        $this->send('/api/saveArticle', ['id' => $article->id, 'title' => 'V3'])
            ->assertStatus(200)
            ->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame('V3', $article->fresh()->title);
    }

    public function test_upload_creation_replayed_stores_a_single_file(): void
    {
        $this->admin();

        $send = fn () => $this->send('/api/saveArticle', $this->articlePayload([
            'image' => UploadedFile::fake()->image('cover.jpg', 100, 100),
        ]));

        $send()->assertStatus(200);
        $send()->assertStatus(200)->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(1, Article::count());
        $this->assertCount(1, array_diff($this->articleFiles(), $this->beforeFiles));
    }

    public function test_concurrent_request_while_lock_held_returns_409(): void
    {
        $user = $this->admin();
        $key = hash('sha256', implode('|', [$user->getAuthIdentifier(), 'POST', 'api/saveArticle', self::KEY]));
        $lock = Cache::lock('idem:lock:' . $key, 30);
        $this->assertTrue($lock->get());

        $this->send('/api/saveArticle', $this->articlePayload())
            ->assertStatus(409)
            ->assertHeader('Retry-After')
            ->assertJson(['success' => false, 'message' => 'Requête en cours de traitement']);

        $lock->release();
        $this->assertSame(0, Article::count());
    }

    public function test_cache_failure_lets_request_through(): void
    {
        $this->admin();
        Cache::shouldReceive('lock')->andThrow(new \RuntimeException('cache down'));

        $this->send('/api/saveArticle', $this->articlePayload())->assertStatus(200);
        $this->assertSame(1, Article::count());
    }
}
