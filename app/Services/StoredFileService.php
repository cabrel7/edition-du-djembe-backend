<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Suppression SÛRE de fichiers uploadés précédemment (remplacement / retrait).
 *
 * Garanties : seul le basename de la valeur stockée est utilisé, dans le répertoire
 * attendu fourni par le code (jamais un chemin venant du client) ; les URL externes
 * et valeurs vides sont ignorées ; un échec n'est jamais remonté à l'utilisateur
 * (warning loggué).
 */
class StoredFileService
{
    private const EXTERNAL_REFERENCE = '#^(?:[a-z][a-z0-9+.\-]*:)?//#i';

    /**
     * @param bool $private true : storage/app/private/<dir> puis repli legacy public/<dir>
     */
    public function delete(?string $stored, string $directory, bool $private = false): bool
    {
        $name = $this->safeName($stored);
        if ($name === null) {
            return false;
        }

        $candidates = [public_path($directory . '/' . $name)];
        if ($private) {
            array_unshift($candidates, storage_path('app/private/' . $directory . '/' . $name));
        }

        $deleted = false;
        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }
            try {
                if (@unlink($path)) {
                    $deleted = true;
                } else {
                    Log::warning('Suppression de fichier remplacé impossible', ['file' => $name, 'directory' => $directory]);
                }
            } catch (\Throwable $e) {
                Log::warning('Suppression de fichier remplacé en échec', ['file' => $name, 'error' => $e->getMessage()]);
            }
        }

        return $deleted;
    }

    private function safeName(?string $stored): ?string
    {
        if ($stored === null || trim($stored) === '' || preg_match(self::EXTERNAL_REFERENCE, $stored) === 1) {
            return null;
        }

        $name = basename(str_replace('\\', '/', $stored));

        return in_array($name, ['', '.', '..'], true) ? null : $name;
    }
}
