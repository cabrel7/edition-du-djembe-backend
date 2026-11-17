<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute books.extension (alignée sur memoirs.extension : VARCHAR(255) NULL)
 * et rétro-remplit les livres existants à partir du nom de fichier `document`.
 *
 * - Idempotente : hasColumn avant ajout / suppression ; le backfill ne touche
 *   que les lignes dont extension IS NULL (rejouable sans écraser).
 * - Aucun accès disque : l'extension est déduite du nom stocké en base.
 * - Lignes soft-deleted incluses (DB::table ignore les scopes Eloquent).
 * - down() ne supprime QUE la colonne ; les autres données sont intactes.
 */
return new class extends Migration
{
    private const TABLE = 'books';
    private const COLUMN = 'extension';
    private const CHUNK_SIZE = 500;
    private const EXTENSION_PATTERN = '/^[a-z0-9]{1,10}$/';

    public function up(): void
    {
        if (!Schema::hasColumn(self::TABLE, self::COLUMN)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string(self::COLUMN)->nullable();
            });
        }

        $this->backfillExtensions();
    }

    public function down(): void
    {
        if (Schema::hasColumn(self::TABLE, self::COLUMN)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn(self::COLUMN);
            });
        }
    }

    /**
     * Par lots (chunkById, mémoire constante) ; un UPDATE par extension distincte
     * et par lot (whereIn sur les ids), requêtes paramétrées.
     */
    private function backfillExtensions(): void
    {
        DB::table(self::TABLE)
            ->whereNotNull('document')
            ->where('document', '!=', '')
            ->whereNull(self::COLUMN)
            ->chunkById(self::CHUNK_SIZE, function ($rows) {
                $idsByExtension = [];

                foreach ($rows as $row) {
                    $extension = $this->extensionFromFilename((string) $row->document);
                    if ($extension !== null) {
                        $idsByExtension[$extension][] = $row->id;
                    }
                }

                foreach ($idsByExtension as $extension => $ids) {
                    DB::table(self::TABLE)
                        ->whereIn('id', $ids)
                        ->whereNull(self::COLUMN)
                        ->update([self::COLUMN => (string) $extension]);
                }
            }, 'id');
    }

    /**
     * Partie après le dernier point du nom de fichier, en minuscules,
     * liste blanche [a-z0-9]{1,10}. null si absente ou invalide.
     */
    private function extensionFromFilename(string $filename): ?string
    {
        $basename = basename(str_replace('\\', '/', trim($filename)));
        $dot = strrpos($basename, '.');

        // Pas de point, point initial (fichier caché) ou point final : pas d'extension.
        if ($dot === false || $dot === 0) {
            return null;
        }

        $extension = strtolower(substr($basename, $dot + 1));

        return preg_match(self::EXTENSION_PATTERN, $extension) === 1 ? $extension : null;
    }
};
