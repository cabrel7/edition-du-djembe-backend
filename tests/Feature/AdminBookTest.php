<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Categorie;
use App\Models\Domaine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminBookTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 1]);
        Passport::actingAs($user);

        return $user;
    }

    private function validBookPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Les tambours de minuit',
            'author' => 'Fatou Ndiaye',
            'category' => Categorie::factory()->create()->id,
            'description' => 'Un roman envoûtant sur les traditions.',
            'price' => 19.99,
            'isbn' => '978-1-23456-789-0',
            'amazon_url' => 'https://www.amazon.fr/dp/B012345678',
            'pages' => 240,
            'document' => UploadedFile::fake()->create('livre.pdf', 100, 'application/pdf'),
            'extraitdocument' => UploadedFile::fake()->create('extrait.pdf', 50, 'application/pdf'),
        ], $overrides);
    }

    public function test_getbook_requires_auth_and_admin(): void
    {
        $this->getJson('/api/getbook')->assertStatus(401);

        Passport::actingAs(User::factory()->create(['role' => 0]));
        $this->getJson('/api/getbook')->assertStatus(403);
    }

    public function test_admin_can_create_book_with_amazon_url(): void
    {
        $this->admin();

        $response = $this->postJson('/api/saveBook', $this->validBookPayload());

        $response->assertStatus(200);
        $this->assertDatabaseHas('books', [
            'title' => 'Les tambours de minuit',
            'amazon_url' => 'https://www.amazon.fr/dp/B012345678',
        ]);
    }

    public function test_save_book_rejects_invalid_amazon_url(): void
    {
        $this->admin();

        $response = $this->postJson('/api/saveBook', $this->validBookPayload([
            'amazon_url' => 'ce-nest-pas-une-url',
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['amazon_url']);
    }

    public function test_partial_update_does_not_null_other_columns(): void
    {
        $this->admin();
        $book = Book::factory()->create(['title' => 'Titre original', 'status' => 1]);

        // Toggle de statut : seul {id, status} est envoyé
        $this->postJson('/api/saveBook', ['id' => $book->id, 'status' => 0])
            ->assertStatus(200);

        $book->refresh();
        $this->assertSame(0, (int) $book->status);
        $this->assertSame('Titre original', $book->title, 'Le titre ne doit pas être nullé par une update partielle');
        $this->assertNotNull($book->price);
        $this->assertNotNull($book->isbn);
    }

    public function test_domaine_partial_update_keeps_description(): void
    {
        $this->admin();
        $domaine = Domaine::factory()->create(['description' => 'Description importante', 'is_active' => true]);

        $this->postJson('/api/saveDomaine', [
            'id' => $domaine->id,
            'name' => $domaine->name,
            'is_active' => false,
        ])->assertStatus(200);

        $domaine->refresh();
        $this->assertFalse((bool) $domaine->is_active);
        $this->assertSame('Description importante', $domaine->description);
    }

    public function test_admin_can_delete_book(): void
    {
        $this->admin();
        $book = Book::factory()->create();

        $this->deleteJson("/api/deleteBook/{$book->id}")->assertStatus(200);
        $this->assertSoftDeleted('books', ['id' => $book->id]);
    }
}
