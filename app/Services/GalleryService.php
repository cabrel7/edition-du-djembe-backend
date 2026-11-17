<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\UploadConstraints;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Galerie d'images d'une revue de presse : ajout idempotent (hash du contenu),
 * retrait des images non conservées, retrait unitaire, nettoyage en cas d'échec.
 */
class GalleryService
{
    public const GALLERY_DIR = 'press-reviews/gallery';

    private const HASH_ALGO = 'sha256';

    public function __construct(private readonly StoredFileService $files)
    {
    }

    /**
     * Retire de la galerie les images absentes de la liste à conserver
     * (JSON ou tableau envoyé par le client). Seules des valeurs déjà présentes en base
     * sont considérées (jamais de chemin client). Une image retirée dont le contenu est
     * ré-envoyé dans la même requête est conservée (retry après réponse perdue).
     * Aucune suppression disque ici : l'appelant supprime $removed APRÈS persistance.
     *
     * @param string[] $current
     * @param UploadedFile[] $uploads
     * @param string[] $removed rempli avec les chemins à supprimer
     * @return string[]
     */
    public function prune(array $current, mixed $keepInput, array $uploads, array &$removed): array
    {
        $keep = is_string($keepInput) ? json_decode($keepInput, true) : $keepInput;
        // Repli sûr : liste illisible = aucune suppression (jamais de purge sur entrée malformée).
        if (!is_array($keep)) {
            return $current;
        }
        $keep = array_map(fn ($v) => basename((string) $v), array_filter($keep, 'is_scalar'));

        $uploadHashes = [];
        foreach ($uploads as $file) {
            $uploadHashes[hash_file(self::HASH_ALGO, $file->getRealPath())] = true;
        }

        $kept = [];
        foreach ($current as $path) {
            $hash = $this->hash($path);
            if (in_array(basename($path), $keep, true) || ($hash !== null && isset($uploadHashes[$hash]))) {
                $kept[] = $path;
                continue;
            }
            $removed[] = $path;
        }

        return $kept;
    }

    /**
     * Ajout IDEMPOTENT : un fichier dont le contenu (sha256) est déjà dans la galerie
     * de cette revue — ou déjà vu dans la même requête — n'est ni ajouté ni copié.
     * Si un déplacement échoue en cours de lot, les fichiers déjà déplacés sont supprimés
     * et une GalleryStorageException (message sans chemin serveur) est levée.
     *
     * @param string[] $current
     * @param UploadedFile[]|UploadedFile $uploads
     * @return string[] galerie finale complète
     */
    public function merge(array $current, array|UploadedFile $uploads): array
    {
        $uploads = is_array($uploads) ? $uploads : [$uploads];
        $seen = [];
        foreach ($current as $path) {
            $hash = $this->hash($path);
            if ($hash !== null) {
                $seen[$hash] = true;
            }
        }

        $moved = [];
        try {
            foreach ($uploads as $file) {
                $hash = hash_file(self::HASH_ALGO, $file->getRealPath());
                if ($hash === false || isset($seen[$hash])) {
                    continue;
                }
                $seen[$hash] = true;
                $filename = Str::random(20) . '.' . $this->extensionOf($file);
                $this->moveUpload($file, public_path(self::GALLERY_DIR), $filename);
                $moved[] = self::GALLERY_DIR . '/' . $filename;
            }
        } catch (\Throwable $e) {
            $this->deleteFiles($moved);
            Log::error('Échec du déplacement d\'une image de galerie', ['error' => $e->getMessage()]);

            throw new GalleryStorageException('Erreur lors de l\'enregistrement des images de la galerie', 0, $e);
        }

        return array_values(array_merge($current, $moved));
    }

    /**
     * Galerie sans l'image $filename (basename) si — et seulement si — elle appartient à $current.
     * null = l'image n'est pas dans cette galerie.
     *
     * @param string[] $current
     * @return array{0: string[], 1: string}|null [nouvelle galerie, chemin retiré]
     */
    public function without(array $current, string $filename): ?array
    {
        $filename = basename($filename);
        foreach ($current as $index => $path) {
            if (basename((string) $path) === $filename) {
                $rest = $current;
                unset($rest[$index]);

                return [array_values($rest), (string) $path];
            }
        }

        return null;
    }

    /** @param string[] $paths chemins relatifs stockés en base */
    public function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->files->delete($path, self::GALLERY_DIR);
        }
    }

    /** Déplacement isolé : point d'extension (tests d'échec en cours de lot). */
    protected function moveUpload(UploadedFile $file, string $directory, string $filename): void
    {
        $file->move($directory, $filename);
    }

    private function extensionOf(UploadedFile $file): string
    {
        $allowed = explode(',', UploadConstraints::IMAGE_MIMES);
        $guessed = strtolower((string) $file->guessExtension());

        return in_array($guessed, $allowed, true) ? $guessed : $allowed[0];
    }

    private function hash(string $relativePath): ?string
    {
        // basename : on ne résout jamais hors du dossier galerie, quelle que soit la valeur stockée/envoyée.
        $full = public_path(self::GALLERY_DIR . '/' . basename($relativePath));

        return is_file($full) ? (hash_file(self::HASH_ALGO, $full) ?: null) : null;
    }
}
