<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImageVariantService;
use App\Services\ImageVariantStatus;
use Illuminate\Console\Command;

/**
 * Génère les variantes WebP manquantes des images publiques (liste blanche config/images.php).
 * DRY-RUN par défaut : rien n'est écrit sans --apply. Idempotente, verrou anti-concurrence.
 * Recommandé : `nice -n 19 ionice -c3 php artisan app:optimize-images --apply`.
 */
class OptimizeImages extends Command
{
    /** Ratio WebP/original utilisé UNIQUEMENT pour l'estimation du dry-run (mesuré en --apply). */
    private const ESTIMATED_WEBP_RATIO = 0.30;
    private const BYTES_PER_MB = 1048576;
    private const MAX_LISTED_SKIPPED = 15;

    protected $signature = 'app:optimize-images
        {--dry-run : Simulation (comportement par défaut) : n\'écrit rien}
        {--apply : Écrit réellement les variantes .webp}
        {--force : Régénère même si la variante est à jour}
        {--dir= : Limite à un répertoire de la liste blanche (ex. books/covers)}
        {--limit= : Nombre maximum de fichiers à traiter}';

    protected $description = 'Génère les variantes WebP (<fichier>.webp) des images publiques (dry-run par défaut)';

    private const LOCK_ACQUIRED = 0;
    private const LOCK_BUSY = 1;
    private const LOCK_ERROR = 2;

    /** @var resource|null */
    private $lock = null;

    private string $lockError = '';

    public function handle(ImageVariantService $service): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && $this->option('dry-run')) {
            $this->error('--apply et --dry-run sont incompatibles.');

            return self::FAILURE;
        }

        $directories = $this->resolveDirectories($service);
        $limit = $this->option('limit');
        if ($directories === null || ($limit !== null && (!ctype_digit((string) $limit) || (int) $limit < 1))) {
            $this->error($directories === null ? 'Répertoire hors liste blanche.' : '--limit doit être un entier >= 1.');

            return self::FAILURE;
        }

        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $this->error('Extension GD avec support WebP indisponible : rien à faire.');

            return self::FAILURE;
        }
        $lock = $this->acquireLock();
        if ($lock !== self::LOCK_ACQUIRED) {
            $this->error($lock === self::LOCK_BUSY
                ? 'Une autre exécution est déjà en cours.'
                : 'Impossible d\'ouvrir le fichier de verrou : ' . $this->lockError);

            return self::FAILURE;
        }

        try {
            $failed = $this->optimize($service, $directories, $apply, $limit === null ? null : (int) $limit);
        } finally {
            $this->releaseLock();
        }

        // Code non nul si au moins une image n'a pas pu être traitée (supervision cron).
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param string[] $directories */
    private function optimize(ImageVariantService $service, array $directories, bool $apply, ?int $limit): int
    {
        $force = (bool) $this->option('force');
        $counts = array_fill_keys(array_map(static fn (ImageVariantStatus $s) => $s->value, ImageVariantStatus::cases()), 0);
        $counts['planned'] = 0;
        $before = 0;
        $after = 0;
        $skippedReasons = [];
        $skippedFiles = [];
        $handled = 0;
        $base = $service->baseDirectory();
        $temps = $this->purgeStaleTemps($service, $base, $directories, $apply);

        foreach ($directories as $directory) {
            foreach ($this->files($base . '/' . $directory) as $path) {
                $skip = $service->plan($path, $force);
                if ($skip !== null) {
                    $counts[$skip->status->value]++;
                    $reason = $skip->reason ?? $skip->status->value;
                    $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;
                    $skippedFiles[] = $directory . '/' . basename($path) . ' (' . $reason . ')';

                    continue;
                }
                if ($limit !== null && $handled >= $limit) {
                    break 2;
                }
                $handled++;

                if (!$apply) {
                    $counts['planned']++;
                    $before += (int) filesize($path);

                    continue;
                }

                $result = $service->generate($path, $force);
                $counts[$result->status->value]++;
                $before += $result->bytesBefore;
                $after += $result->isCreated() ? $result->bytesAfter : $result->bytesBefore;
                if (!$result->isCreated()) {
                    $reason = $result->reason ?? $result->status->value;
                    $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;
                    $skippedFiles[] = $directory . '/' . basename($path) . ' (' . $reason . ')';
                }
            }
        }

        $this->report($apply, $counts, $before, $after, $skippedReasons, $skippedFiles);
        $this->reportTemps($temps, $apply);

        return $counts['failed'];
    }

    /**
     * Temporaires orphelins (motif exact, > 1 h) des répertoires de la liste blanche.
     *
     * @param string[] $directories
     * @return array{found: string[], deleted: int}
     */
    private function purgeStaleTemps(ImageVariantService $service, string $base, array $directories, bool $apply): array
    {
        $found = [];
        $deleted = 0;
        foreach ($directories as $directory) {
            foreach ($service->staleTempFiles($base . '/' . $directory) as $path) {
                $found[] = $directory . '/' . basename($path);
                if ($apply && @unlink($path)) {
                    $deleted++;
                }
            }
        }

        return ['found' => $found, 'deleted' => $deleted];
    }

    /** @param array{found: string[], deleted: int} $temps */
    private function reportTemps(array $temps, bool $apply): void
    {
        if ($temps['found'] === []) {
            return;
        }
        $this->line($apply
            ? sprintf('%d fichier(s) temporaire(s) orphelin(s) supprimé(s) sur %d détecté(s).', $temps['deleted'], count($temps['found']))
            : sprintf('%d fichier(s) temporaire(s) orphelin(s) détecté(s) (supprimés avec --apply) :', count($temps['found'])));
        if (!$apply) {
            foreach ($temps['found'] as $name) {
                $this->line('  - ' . $name);
            }
        }
    }

    /** @param array<string,int> $counts @param array<string,int> $reasons @param string[] $skippedFiles */
    private function report(bool $apply, array $counts, int $before, int $after, array $reasons, array $skippedFiles): void
    {
        $mb = static fn (int|float $bytes): string => number_format($bytes / self::BYTES_PER_MB, 2, '.', '');

        if (!$apply) {
            $estimated = (int) round($before * self::ESTIMATED_WEBP_RATIO);
            $this->warn('DRY-RUN : aucun fichier écrit (utiliser --apply).');
            $this->table(['À traiter', 'Ignorés', 'Mo avant', 'Mo après (estimé)', 'Gain estimé Mo'], [[
                $counts['planned'],
                $counts['skipped'] + $counts['unsupported'] + $counts['failed'],
                $mb($before),
                $mb($estimated),
                $mb($before - $estimated),
            ]]);
        } else {
            $this->table(['Créées', 'Ignorées', 'Plus lourdes (supprimées)', 'Non supportées', 'Échecs', 'Mo avant', 'Mo après'], [[
                $counts['created'],
                $counts['skipped'],
                $counts['too_large'],
                $counts['unsupported'],
                $counts['failed'],
                $mb($before),
                $mb($after),
            ]]);
        }

        if ($reasons !== []) {
            $this->line('Ignorés par motif : ' . implode(', ', array_map(static fn ($r, $n) => "{$r}={$n}", array_keys($reasons), $reasons)));
            foreach (array_slice($skippedFiles, 0, $this->output->isVerbose() ? PHP_INT_MAX : self::MAX_LISTED_SKIPPED) as $line) {
                $this->line('  - ' . $line);
            }
        }
    }

    /** @return string[]|null null = --dir invalide */
    private function resolveDirectories(ImageVariantService $service): ?array
    {
        $allowed = $service->allowedDirectories();
        $dir = $this->option('dir');
        if ($dir === null || $dir === '') {
            return $allowed;
        }

        $dir = trim((string) $dir, '/');

        return in_array($dir, $allowed, true) ? [$dir] : null;
    }

    /** @return \Generator<string> fichiers directs (sans symlink ni sous-dossier), ordre stable */
    private function files(string $directory): \Generator
    {
        $names = is_dir($directory) ? (scandir($directory) ?: []) : [];
        foreach ($names as $name) {
            $path = $directory . '/' . $name;
            if ($name !== '.' && $name !== '..' && !is_link($path) && is_file($path)) {
                yield $path;
            }
        }
    }

    /**
     * Distingue verrou pris (BUSY) et impossibilité d'ouvrir/créer le fichier (ERROR, message dans lockError).
     * Fichier créé en 0664 ; chemin par défaut storage/framework, repli sur le dossier temporaire système
     * si ce dossier n'est pas inscriptible (jamais de repli quand le chemin est fixé par config).
     */
    private function acquireLock(): int
    {
        $configured = config('images.lock_file');
        $candidates = $configured ? [(string) $configured] : [storage_path('framework/optimize-images.lock'), sys_get_temp_dir() . '/optimize-images-' . md5(base_path()) . '.lock'];

        foreach ($candidates as $file) {
            $handle = $this->openLockFile($file);
            if ($handle === null) {
                continue;
            }
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);

                return self::LOCK_BUSY;
            }
            $this->lock = $handle;

            return self::LOCK_ACQUIRED;
        }

        $this->lockError = $candidates[array_key_last($candidates)] . ' (' . ($this->lockError ?: 'ouverture refusée') . ')';

        return self::LOCK_ERROR;
    }

    /** @return resource|null */
    private function openLockFile(string $file)
    {
        $existed = file_exists($file);
        // 'c' crée sans tronquer ; repli lecture seule (flock n'exige pas l'écriture) pour un fichier créé par un autre utilisateur.
        $handle = @fopen($file, 'c') ?: ($existed ? @fopen($file, 'r') : false);
        if ($handle === false) {
            $this->lockError = (string) (error_get_last()['message'] ?? 'ouverture refusée');

            return null;
        }
        if (!$existed) {
            @chmod($file, 0664);
        }

        return $handle;
    }

    private function releaseLock(): void
    {
        if (is_resource($this->lock)) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
        }
        $this->lock = null;
    }
}
