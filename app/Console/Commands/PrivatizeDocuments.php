<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Déplace les documents sensibles du web root vers storage/app
 * (hors portée de tout serveur web). Idempotent : ne bouge que ce qui existe encore.
 *
 *   php artisan app:privatize-documents
 */
class PrivatizeDocuments extends Command
{
    protected $signature = 'app:privatize-documents';

    protected $description = 'Déplace books/documents, books/extrait_documents, memoirs/documents et submissions hors web root';

    public function handle(): int
    {
        // Destination = là où les contrôleurs lisent réellement : private/ pour
        // les documents (privateOrLegacyPath), mais storage/app/submissions pour
        // les soumissions (SubmissionController lit storage/app/<SUBMISSIONS_DIR>).
        $moves = [
            'books/documents' => 'private/books/documents',
            'books/extrait_documents' => 'private/books/extrait_documents',
            'memoirs/documents' => 'private/memoirs/documents',
            'submissions' => 'submissions',
        ];

        $total = 0;
        foreach ($moves as $publicRel => $storageRel) {
            $from = public_path($publicRel);
            $to = storage_path('app/' . $storageRel);

            if (!is_dir($from)) {
                continue;
            }
            File::ensureDirectoryExists($to, 0750);

            foreach (File::files($from) as $file) {
                $target = $to . '/' . $file->getFilename();
                if (is_file($target)) {
                    // Destination déjà remplie : ne jamais écraser, on supprime la source.
                    File::delete($file->getPathname());
                    $this->line("  doublon supprimé : {$publicRel}/{$file->getFilename()}");
                    continue;
                }
                rename($file->getPathname(), $target);
                $total++;
            }
            $this->info("{$publicRel} → storage/app/{$storageRel}");
        }

        $this->info("Terminé : {$total} fichier(s) déplacé(s) hors web root.");

        $normalized = $this->normalizeColumn('books', 'document')
            + $this->normalizeColumn('books', 'extraitdocument')
            + $this->normalizeColumn('memoirs', 'document');
        $this->info("Colonnes normalisées (nom de fichier seul) : {$normalized} ligne(s).");

        return self::SUCCESS;
    }

    /**
     * Les valeurs legacy stockent un chemin relatif ("books/documents/x.pdf") ;
     * le code récent n'y stocke que le nom. On ramène tout au nom seul pour
     * que la reconstruction de chemin par les contrôleurs reste juste.
     */
    private function normalizeColumn(string $table, string $column): int
    {
        $count = 0;
        $rows = DB::table($table)->whereNotNull($column)->where($column, 'like', '%/%')->get(['id', $column]);

        foreach ($rows as $row) {
            $basename = basename($row->$column);
            if ($basename !== '' && $basename !== $row->$column) {
                DB::table($table)->where('id', $row->id)->update([$column => $basename]);
                $count++;
            }
        }

        return $count;
    }
}
