<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Categorie;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookExtensionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION_FILE = '2026_11_17_000000_add_extension_to_books_table.php';

    private function migration(): Migration
    {
        return require database_path('migrations/' . self::MIGRATION_FILE);
    }

    private function insertBook(int $categoryId, string $slug, ?string $document, array $extra = []): int
    {
        $now = now();

        return DB::table('books')->insertGetId(array_merge([
            'title' => 'Titre ' . $slug,
            'author' => 'Auteur',
            'category' => $categoryId,
            'description' => 'Desc',
            'slug' => $slug,
            'price' => 10,
            'isbn' => 'isbn-' . $slug,
            'pages' => 100,
            'document' => $document,
            'created_at' => $now,
            'updated_at' => $now,
        ], $extra));
    }

    protected function tearDown(): void
    {
        // Laisse le schéma dans son état final attendu quoi qu'il arrive.
        if (!Schema::hasColumn('books', 'extension')) {
            $this->migration()->up();
        }

        parent::tearDown();
    }

    public function test_up_creates_nullable_string_column(): void
    {
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('books', 'extension'));

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('books', 'extension'));
        $this->assertSame('string', Schema::getColumnType('books', 'extension'));

        $categoryId = Categorie::factory()->create()->id;
        $id = $this->insertBook($categoryId, 'nullable-ok', 'x.pdf');
        DB::table('books')->where('id', $id)->update(['extension' => null]);
        $this->assertNull(DB::table('books')->where('id', $id)->value('extension'));
    }

    public function test_backfill_derives_extension_from_document_filename(): void
    {
        $categoryId = Categorie::factory()->create()->id;
        $this->migration()->down();

        $upper = $this->insertBook($categoryId, 'upper', 'a.PDF');
        $noExt = $this->insertBook($categoryId, 'no-ext', 'fichiersansextension');
        $nullDoc = $this->insertBook($categoryId, 'null-doc', null);
        $emptyDoc = $this->insertBook($categoryId, 'empty-doc', '');
        $path = $this->insertBook($categoryId, 'path', 'books/documents/abc.def.Epub');
        $badChars = $this->insertBook($categoryId, 'bad-chars', 'abc.p-d f');
        $tooLong = $this->insertBook($categoryId, 'too-long', 'abc.' . str_repeat('a', 11));
        $trailingDot = $this->insertBook($categoryId, 'trailing-dot', 'abc.');
        $trashed = $this->insertBook($categoryId, 'trashed', 'old.docx', ['deleted_at' => now()]);

        $this->migration()->up();

        $ext = fn (int $id) => DB::table('books')->where('id', $id)->value('extension');

        $this->assertSame('pdf', $ext($upper));
        $this->assertNull($ext($noExt));
        $this->assertNull($ext($nullDoc));
        $this->assertNull($ext($emptyDoc));
        $this->assertSame('epub', $ext($path));
        $this->assertNull($ext($badChars));
        $this->assertNull($ext($tooLong));
        $this->assertNull($ext($trailingDot));
        $this->assertSame('docx', $ext($trashed));
    }

    public function test_backfill_is_batched_and_never_overwrites_existing_values(): void
    {
        $categoryId = Categorie::factory()->create()->id;
        $keep = $this->insertBook($categoryId, 'keep', 'k.pdf');
        DB::table('books')->where('id', $keep)->update(['extension' => 'docx']);

        // Plus que la taille de lot (500) pour exercer chunkById sur plusieurs pages.
        $now = now();
        $rows = [];
        for ($i = 0; $i < 1100; $i++) {
            $rows[] = [
                'title' => "T$i", 'author' => 'A', 'category' => $categoryId, 'description' => 'D',
                'slug' => "bulk-$i", 'price' => 1, 'isbn' => "bulk-isbn-$i", 'pages' => 1,
                'document' => "f$i.TXT", 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('books')->insert($chunk);
        }

        // Rejoue up() : la colonne existe déjà, le backfill complète sans écraser.
        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('docx', DB::table('books')->where('id', $keep)->value('extension'));
        $this->assertSame(1100, DB::table('books')->where('extension', 'txt')->count());
        $this->assertSame(0, DB::table('books')->where('slug', 'like', 'bulk-%')->whereNull('extension')->count());
    }

    public function test_up_is_idempotent_when_column_already_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('books', 'extension'));

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('books', 'extension'));
    }

    public function test_down_drops_only_the_column_and_is_idempotent(): void
    {
        $categoryId = Categorie::factory()->create()->id;
        $id = $this->insertBook($categoryId, 'keep-data', 'x.pdf', ['extension' => 'pdf']);

        $this->migration()->down();

        $this->assertFalse(Schema::hasColumn('books', 'extension'));
        $row = DB::table('books')->where('id', $id)->first();
        $this->assertSame('x.pdf', $row->document);
        $this->assertSame('Titre keep-data', $row->title);
        $this->assertSame('keep-data', $row->slug);

        // Second down() sans colonne : ne lève pas d'erreur.
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('books', 'extension'));
    }

    public function test_extension_is_mass_assignable_on_book_model(): void
    {
        $this->assertContains('extension', (new Book())->getFillable());

        $book = Book::factory()->create(['extension' => 'pdf']);

        $this->assertSame('pdf', $book->fresh()->extension);
    }
}
