<?php

namespace Tests\Feature;

use App\Models\PressReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PressReviewDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private const DIR = 'press-reviews/documents';

    /** @var string[] */
    private array $before = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->before = glob(public_path(self::DIR . '/*')) ?: [];
    }

    protected function tearDown(): void
    {
        foreach (array_diff(glob(public_path(self::DIR . '/*')) ?: [], $this->before) as $leak) {
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

    /** @return array{0: PressReview, 1: string[]} revue avec N documents réels sur disque */
    private function reviewWithDocs(int $n = 2): array
    {
        $docs = [];
        $paths = [];
        for ($i = 1; $i <= $n; $i++) {
            $rel = self::DIR . '/zz-doc-' . uniqid() . "-{$i}.pdf";
            if (!is_dir(public_path(self::DIR))) {
                mkdir(public_path(self::DIR), 0775, true);
            }
            file_put_contents(public_path($rel), "%PDF-1.4 doc {$i} " . uniqid());
            $docs[] = ['path' => $rel, 'name' => "d{$i}.pdf", 'size' => 10, 'type' => 'application/pdf'];
            $paths[] = $rel;
        }
        $review = PressReview::create([
            'title' => 'T', 'slug' => 'r-' . uniqid(), 'summary' => 's', 'content' => 'c',
            'category' => 'article', 'status' => 'draft', 'documents' => $docs,
        ]);

        return [$review, $paths];
    }

    private function putReview(PressReview $review, array $params, array $files = []): \Illuminate\Testing\TestResponse
    {
        return $this->call('PUT', "/api/admin/press-reviews/{$review->id}", $params, [], $files, ['HTTP_ACCEPT' => 'application/json']);
    }

    private function paths(PressReview $review): array
    {
        return array_column($review->fresh()->documents ?? [], 'path');
    }

    public function test_existing_documents_removes_missing_ones_after_update(): void
    {
        $this->admin();
        [$review, [$keep, $drop]] = $this->reviewWithDocs();

        $this->putReview($review, ['existing_documents' => json_encode([$keep])])->assertOk();

        $this->assertSame([$keep], $this->paths($review));
        $this->assertFileExists(public_path($keep));
        $this->assertFileDoesNotExist(public_path($drop));
    }

    public function test_explicit_empty_list_removes_all(): void
    {
        $this->admin();
        [$review, $paths] = $this->reviewWithDocs();

        $this->putReview($review, ['existing_documents' => '[]'])->assertOk();

        $this->assertSame([], $this->paths($review));
        foreach ($paths as $p) {
            $this->assertFileDoesNotExist(public_path($p));
        }
    }

    public function test_unreadable_list_or_absent_key_removes_nothing(): void
    {
        $this->admin();
        [$review, $paths] = $this->reviewWithDocs();

        $this->putReview($review, ['existing_documents' => '{pas du json'])->assertOk();
        $this->putReview($review, ['existing_documents' => '"une chaine"'])->assertOk();
        $this->putReview($review, ['title' => 'Autre'])->assertOk();

        $this->assertSame($paths, $this->paths($review));
        foreach ($paths as $p) {
            $this->assertFileExists(public_path($p));
        }
    }

    public function test_client_paths_cannot_delete_foreign_files(): void
    {
        $this->admin();
        [$review, [$keep]] = $this->reviewWithDocs(1);
        $victim = base_path('zz-victim.txt');
        file_put_contents($victim, 'x');

        try {
            $this->putReview($review, ['existing_documents' => json_encode([$keep, '../../.env', 'zz-victim.txt'])])->assertOk();
            $this->assertFileExists($victim);
            $this->assertFileExists(base_path('.env'));
            $this->assertSame([$keep], $this->paths($review));
        } finally {
            File::delete($victim);
        }
    }

    public function test_other_reviews_documents_are_untouched(): void
    {
        $this->admin();
        [$a] = $this->reviewWithDocs(1);
        [$b, [$bPath]] = $this->reviewWithDocs(1);

        $this->putReview($a, ['existing_documents' => json_encode([$bPath])])->assertOk();

        $this->assertSame([], $this->paths($a));
        $this->assertFileExists(public_path($bPath));
        $this->assertSame([$bPath], $this->paths($b));
    }

    public function test_removed_document_resent_in_same_request_is_kept_and_new_ones_added(): void
    {
        $this->admin();
        [$review, [$p]] = $this->reviewWithDocs(1);
        $same = UploadedFile::fake()->createWithContent('same.pdf', file_get_contents(public_path($p)));
        $fresh = UploadedFile::fake()->createWithContent('new.pdf', '%PDF-1.4 brand new');

        $res = $this->putReview($review, ['existing_documents' => '[]'], ['documents' => [$same, $fresh]])->assertOk();

        $paths = $this->paths($review);
        $this->assertCount(2, $paths);
        $this->assertContains($p, $paths);
        $this->assertFileExists(public_path($p));
        $this->assertCount(2, $res->json('data.documents'));
    }

    public function test_adding_documents_without_list_keeps_existing(): void
    {
        $this->admin();
        [$review, [$p]] = $this->reviewWithDocs(1);

        $this->putReview($review, [], ['documents' => [UploadedFile::fake()->createWithContent('n.pdf', '%PDF-1.4 other')]])->assertOk();

        $this->assertCount(2, $this->paths($review));
        $this->assertFileExists(public_path($p));
    }

    public function test_delete_endpoint_removes_document_and_file(): void
    {
        $this->admin();
        [$review, [$keep, $drop]] = $this->reviewWithDocs();

        $this->deleteJson("/api/admin/press-reviews/{$review->id}/documents/" . basename($drop))->assertOk();

        $this->assertSame([$keep], $this->paths($review));
        $this->assertFileDoesNotExist(public_path($drop));
        $this->assertFileExists(public_path($keep));
    }

    public function test_delete_endpoint_404_for_other_review_or_unknown(): void
    {
        $this->admin();
        [$a] = $this->reviewWithDocs(1);
        [$b, [$bPath]] = $this->reviewWithDocs(1);

        $this->deleteJson("/api/admin/press-reviews/{$a->id}/documents/" . basename($bPath))->assertStatus(404);
        $this->deleteJson("/api/admin/press-reviews/{$a->id}/documents/inconnu.pdf")->assertStatus(404);
        $this->deleteJson('/api/admin/press-reviews/999999/documents/x.pdf')->assertStatus(404);

        $this->assertFileExists(public_path($bPath));
        $this->assertSame([$bPath], $this->paths($b));
    }

    public function test_delete_endpoint_requires_auth_and_admin(): void
    {
        [$review, [$p]] = $this->reviewWithDocs(1);
        $url = "/api/admin/press-reviews/{$review->id}/documents/" . basename($p);

        $this->deleteJson($url)->assertStatus(401);

        Passport::actingAs(User::factory()->create(['role' => 0]));
        $this->deleteJson($url)->assertStatus(403);
        $this->assertFileExists(public_path($p));
    }
}
