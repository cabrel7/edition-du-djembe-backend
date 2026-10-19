<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Contrôle non destructif : chaque nom de fichier référencé en base
 * correspond-il à un fichier réellement présent sur le disque ?
 *
 *   php artisan app:check-documents
 *
 * Exit code 1 si un fichier manquant ou mal placé est détecté.
 */
class CheckDocuments extends Command
{
    protected $signature = 'app:check-documents';

    protected $description = 'Vérifie que la base et le disque suivent : documents privés, submissions et images publiques';

    public function handle(): int
    {
        $problems = 0;

        $problems += $this->checkPrivate('books', 'document', 'books/documents');
        $problems += $this->checkPrivate('books', 'extraitdocument', 'books/extrait_documents');
        $problems += $this->checkPrivate('memoirs', 'document', 'memoirs/documents');
        $problems += $this->checkSubmissions();

        // Résolution identique à coverUrl() côté Nuxt : chemin déjà préfixé
        // gardé tel quel, sinon nom seul → répertoire attendu par kind.
        $images = [
            ['books', 'cover', 'books/covers'],
            ['memoirs', 'cover', 'memoirs/covers'],
            ['articles', 'image', 'articles'],
            ['press_reviews', 'cover_image', 'press-reviews/covers'],
            ['videos', 'thumbnail', 'video'],
            ['team_members', 'photo', 'team'],
            ['partners', 'image', 'partners'],
        ];
        foreach ($images as [$table, $column, $dir]) {
            $problems += $this->checkPublicImage($table, $column, $dir);
        }

        if ($problems > 0) {
            $this->newLine();
            $this->error("{$problems} problème(s) détecté(s) : base et disque ne suivent pas.");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('OK : base et disque suivent (aucun fichier manquant ou mal placé).');
        return self::SUCCESS;
    }

    private function checkPrivate(string $table, string $column, string $dir): int
    {
        $rows = DB::table($table)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->get(['id', $column]);

        if ($rows->isEmpty()) {
            $this->line("{$table}.{$column} : aucune valeur.");
            return 0;
        }

        $ok = 0;
        $webRoot = 0;
        $badPath = 0;
        $missing = [];

        foreach ($rows as $row) {
            $value = $row->$column;
            $name = basename($value);

            if ($value !== $name) {
                $badPath++;
            }
            if (is_file(storage_path('app/private/' . $dir . '/' . $name))) {
                $ok++;
            } elseif (is_file(public_path($dir . '/' . $name))) {
                $webRoot++;
                $missing[] = "  WEB ROOT (insecure) {$table}#{$row->id} : {$value}";
            } else {
                $missing[] = "  MANQUE {$table}#{$row->id} : {$value}";
            }
        }

        $this->line(sprintf(
            '%s.%s : %d valeur(s) | privé OK=%d | web root=%d | manquant=%d | chemins non normalisés=%d',
            $table,
            $column,
            count($rows),
            $ok,
            $webRoot,
            count($missing) - $webRoot,
            $badPath,
        ));
        foreach ($missing as $line) {
            $this->line($line);
        }

        return count($missing) + ($badPath > 0 ? 1 : 0);
    }

    private function checkSubmissions(): int
    {
        $rows = DB::table('submissions')->get(['id', 'file_path']);

        if ($rows->isEmpty()) {
            $this->line('submissions : aucune valeur.');
            return 0;
        }

        $ok = 0;
        $problems = [];

        foreach ($rows as $row) {
            if (is_file(storage_path('app/submissions/' . $row->file_path))) {
                $ok++;
            } elseif (is_file(storage_path('app/private/submissions/' . $row->file_path))) {
                $problems[] = "  MAL PLACÉ submissions#{$row->id} : private/submissions/{$row->file_path} (le contrôleur lit app/submissions)";
            } elseif (is_file(public_path('submissions/' . $row->file_path))) {
                $problems[] = "  WEB ROOT submissions#{$row->id} : {$row->file_path}";
            } else {
                $problems[] = "  MANQUE submissions#{$row->id} : {$row->file_path}";
            }
        }

        $this->line(sprintf(
            'submissions : %d valeur(s) | OK=%d | problème=%d',
            count($rows),
            $ok,
            count($problems),
        ));
        foreach ($problems as $line) {
            $this->line($line);
        }

        return count($problems);
    }

    private function checkPublicImage(string $table, string $column, string $dir): int
    {
        $rows = DB::table($table)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->get(['id', $column]);

        if ($rows->isEmpty()) {
            $this->line("{$table}.{$column} : aucune valeur.");
            return 0;
        }

        $ok = 0;
        $missing = [];

        foreach ($rows as $row) {
            $value = $row->$column;
            $relative = str_contains($value, '/') ? $value : $dir . '/' . $value;

            if (is_file(public_path($relative)) || is_file(storage_path('app/public/' . $relative))) {
                $ok++;
            } else {
                $missing[] = "  MANQUE {$table}#{$row->id} : {$relative}";
            }
        }

        $this->line(sprintf(
            '%s.%s : %d valeur(s) | OK=%d | manquant=%d',
            $table,
            $column,
            count($rows),
            $ok,
            count($missing),
        ));
        foreach ($missing as $line) {
            $this->line($line);
        }

        return count($missing);
    }
}
