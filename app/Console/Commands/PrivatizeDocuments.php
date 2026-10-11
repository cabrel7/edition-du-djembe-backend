<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Déplace les documents sensibles du web root vers storage/app/private
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
        $moves = [
            'books/documents' => 'books/documents',
            'books/extrait_documents' => 'books/extrait_documents',
            'memoirs/documents' => 'memoirs/documents',
            'submissions' => 'submissions',
        ];

        $total = 0;
        foreach ($moves as $publicRel => $privateRel) {
            $from = public_path($publicRel);
            $to = storage_path('app/private/' . $privateRel);

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
            $this->info("{$publicRel} → storage/app/private/{$privateRel}");
        }

        $this->info("Terminé : {$total} fichier(s) déplacé(s) hors web root.");

        return self::SUCCESS;
    }
}
