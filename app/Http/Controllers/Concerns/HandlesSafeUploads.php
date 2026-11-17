<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HandlesSafeUploads
{
    /**
     * Fichiers écrits PENDANT la requête courante : [nom, répertoire, privé?].
     * Servent uniquement à nettoyer les nouveaux fichiers si l'enregistrement en base échoue
     * (jamais à supprimer un fichier préexistant). Réinitialisé par beginUploads().
     *
     * @var array<int, array{0: string, 1: string, 2: bool}>
     */
    private array $newUploads = [];

    private function beginUploads(): void
    {
        $this->newUploads = [];
    }

    /** Supprime les fichiers fraîchement écrits (échec d'enregistrement) ; jamais d'exception. */
    private function discardNewUploads(): void
    {
        foreach ($this->newUploads as [$name, $directory, $private]) {
            $this->deleteStoredFile($name, $directory, $private);
        }
        $this->newUploads = [];
    }

    /** Durée max (s) consacrée aux variantes WebP dans UNE requête ; le reste est laissé à app:optimize-images. */
    private const VARIANT_REQUEST_BUDGET_SECONDS = 8.0;

    /**
     * À appeler APRÈS la persistance réussie : variante WebP des images publiques fraîchement
     * écrites. Synchrone, plafonné (mémoire par image + budget de temps) ; ne lève jamais et
     * n'affecte jamais la réponse (les documents/répertoires hors liste blanche sont ignorés).
     */
    private function generateNewImageVariants(): void
    {
        $uploads = $this->newUploads;
        $this->newUploads = [];
        $deadline = microtime(true) + self::VARIANT_REQUEST_BUDGET_SECONDS;

        try {
            $service = app(\App\Services\ImageVariantService::class);
            foreach ($uploads as [$name, $directory, $private]) {
                if ($private) {
                    continue;
                }
                if (microtime(true) > $deadline) {
                    \Illuminate\Support\Facades\Log::info('Budget variantes WebP épuisé : reste à traiter par app:optimize-images');
                    break;
                }
                $service->generateForStored($name, $directory);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Variantes WebP ignorées après upload', ['error' => $e->getMessage()]);
        }
    }

    private function safeExt(UploadedFile $file, string $allowedMimes): string
    {
        $allowed = explode(',', $allowedMimes);
        $guessed = strtolower((string) $file->guessExtension());

        return in_array($guessed, $allowed, true) ? $guessed : $allowed[0];
    }

    private function storeUpload(UploadedFile $file, string $directory, string $allowedMimes): array
    {
        $extension = $this->safeExt($file, $allowedMimes);
        $filename = Str::random(20) . '.' . $extension;
        $file->move(public_path($directory), $filename);
        $this->newUploads[] = [$filename, $directory, false];

        return ['filename' => $filename, 'extension' => $extension];
    }

    /**
     * Upload HORS web root (storage/app/private) : jamais accessible par URL directe.
     * Utiliser pour les documents payants/confidentiels (PDF livres, mémoires…).
     */
    private function storePrivateUpload(UploadedFile $file, string $directory, string $allowedMimes): array
    {
        $extension = $this->safeExt($file, $allowedMimes);
        $filename = Str::random(20) . '.' . $extension;
        $file->move(self::privateDir($directory), $filename);
        $this->newUploads[] = [$filename, $directory, true];

        return ['filename' => $filename, 'extension' => $extension];
    }

    private static function privateDir(string $directory): string
    {
        $dir = storage_path('app/private/' . $directory);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir;
    }

    /**
     * Résout un fichier privé : chemin storage/app/private d'abord,
     * fallback legacy web root pour les fichiers pré-migration (404 sinon).
     */
    private function privateOrLegacyPath(string $relative, string $legacyPublicDir): ?string
    {
        $private = storage_path('app/private/' . $relative);
        if (is_file($private)) {
            return $private;
        }

        $legacy = public_path($legacyPublicDir . '/' . basename($relative));

        return is_file($legacy) ? $legacy : null;
    }

    /** Nom de fichier de téléchargement sûr (pas de séparateur, guillemet ni caractère de contrôle). */
    private function safeDownloadName(string $title, string $extension): string
    {
        $base = trim((string) preg_replace('/[^\p{L}\p{N} ._-]+/u', '_', $title), " ._-");
        $base = mb_substr($base !== '' ? $base : 'document', 0, 150);
        $ext = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $extension));

        return $ext !== '' ? $base . '.' . $ext : $base;
    }

    /**
     * Extension RÉELLE du fichier servi (nom résolu sur disque) ; à défaut l'extension
     * déclarée en base (colonne `extension`, absente sur certaines bases), puis celle du nom stocké.
     */
    private function downloadExtension(string $resolvedPath, ?string $declared = null, ?string $storedName = null): string
    {
        foreach ([pathinfo($resolvedPath, PATHINFO_EXTENSION), (string) $declared, pathinfo((string) $storedName, PATHINFO_EXTENSION)] as $candidate) {
            $clean = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $candidate));
            if ($clean !== '') {
                return $clean;
            }
        }

        return '';
    }

    /**
     * Supprime un fichier REMPLACÉ (après persistance réussie). basename + répertoire attendu
     * uniquement ; URL externe / vide ignorées ; échec = warning loggué, jamais une erreur utilisateur.
     */
    private function deleteStoredFile(?string $stored, string $directory, bool $private = false): void
    {
        app(\App\Services\StoredFileService::class)->delete($stored, $directory, $private);
    }

    /**
     * Booléen robuste depuis la requête : "1"/"0"/"true"/"false"/true/false.
     * null = champ absent (ou valeur illisible) : l'appelant ne doit PAS l'écrire.
     */
    private function presentBool(\Illuminate\Http\Request $request, string $key): ?bool
    {
        if (!$request->exists($key) || $request->input($key) === null) {
            return null;
        }

        return filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /** Remplace "true"/"false"/"on"/"yes"... par un vrai booléen avant validation (règle `boolean`). */
    private function normalizeBoolInput(\Illuminate\Http\Request $request, string $key): void
    {
        $parsed = $this->presentBool($request, $key);
        if ($parsed !== null) {
            $request->merge([$key => $parsed]);
        }
    }

    private function uniqueSlug(string $table, string $title): string
    {
        $base = Str::slug($title) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function validationMessages(): array
    {
        return [
            'required' => 'Le champ :attribute est obligatoire.',
            'string' => 'Le champ :attribute doit être une chaîne de caractères.',
            'integer' => 'Le champ :attribute doit être un nombre entier.',
            'numeric' => 'Le champ :attribute doit être un nombre.',
            'date' => 'Le champ :attribute doit être une date valide.',
            'url' => 'Le champ :attribute doit être une URL valide.',
            'max' => [
                'file' => 'Le fichier :attribute ne doit pas dépasser :max Ko.',
                'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
                'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
            ],
            'min' => [
                'file' => 'Le fichier :attribute est trop petit.',
                'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
                'numeric' => 'Le champ :attribute doit être au minimum :min.',
            ],
            'between' => 'Le champ :attribute doit être compris entre :min et :max.',
            'mimes' => 'Le fichier :attribute doit être de type :values.',
            'image' => 'Le fichier :attribute doit être une image.',
            'file' => 'Le champ :attribute doit être un fichier valide.',
            'exists' => 'La valeur sélectionnée pour :attribute est invalide.',
            'unique' => 'La valeur de :attribute est déjà utilisée.',
            'in' => 'La valeur sélectionnée pour :attribute est invalide.',
            'boolean' => 'Le champ :attribute doit être vrai ou faux.',
            'array' => 'Le champ :attribute doit être une liste valide.',
        ];
    }
}
