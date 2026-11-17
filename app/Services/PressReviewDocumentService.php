<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Documents d'une revue de presse (colonne JSON : [{path,name,size,type}]).
 * Mêmes garanties que GalleryService : jamais de chemin client, liste illisible = aucun retrait,
 * suppression disque faite par l'appelant APRÈS la persistance.
 */
class PressReviewDocumentService
{
    public const DOCUMENTS_DIR = 'press-reviews/documents';

    private const HASH_ALGO = 'sha256';

    public function __construct(private readonly StoredFileService $files)
    {
    }

    /**
     * @param array<int, mixed> $current documents en base
     * @param UploadedFile[] $uploads
     * @param array<int, mixed> $removed rempli avec les entrées à supprimer du disque
     * @return array<int, mixed> documents conservés
     */
    public function prune(array $current, mixed $keepInput, array $uploads, array &$removed): array
    {
        $keep = is_string($keepInput) ? json_decode($keepInput, true) : $keepInput;
        if (!is_array($keep)) {
            return $current;
        }
        $names = [];
        foreach ($keep as $item) {
            $value = is_array($item) ? ($item['path'] ?? null) : $item;
            if (is_string($value) && $value !== '') {
                $names[] = basename(str_replace('\\', '/', $value));
            }
        }

        $uploadHashes = $this->uploadHashes($uploads);
        $kept = [];
        foreach ($current as $doc) {
            $hash = $this->hash($doc);
            if (in_array($this->nameOf($doc), $names, true) || ($hash !== null && isset($uploadHashes[$hash]))) {
                $kept[] = $doc;
                continue;
            }
            $removed[] = $doc;
        }

        return $kept;
    }

    /**
     * Retire un document par basename s'il appartient à $current.
     *
     * @param array<int, mixed> $current
     * @return array{0: array<int, mixed>, 1: mixed}|null [restants, entrée retirée]
     */
    public function without(array $current, string $filename): ?array
    {
        $filename = basename(str_replace('\\', '/', $filename));
        foreach ($current as $index => $doc) {
            if ($this->nameOf($doc) === $filename && $filename !== '') {
                $rest = $current;
                unset($rest[$index]);

                return [array_values($rest), $doc];
            }
        }

        return null;
    }

    /**
     * Uploads dont le contenu n'est pas déjà dans $current (idempotence : retry / doublon).
     *
     * @param array<int, mixed> $current
     * @param UploadedFile[] $uploads
     * @return UploadedFile[]
     */
    public function newUploads(array $current, array $uploads): array
    {
        $seen = [];
        foreach ($current as $doc) {
            $hash = $this->hash($doc);
            if ($hash !== null) {
                $seen[$hash] = true;
            }
        }
        $fresh = [];
        foreach ($uploads as $file) {
            $hash = hash_file(self::HASH_ALGO, $file->getRealPath());
            if ($hash !== false && isset($seen[$hash])) {
                continue;
            }
            if ($hash !== false) {
                $seen[$hash] = true;
            }
            $fresh[] = $file;
        }

        return $fresh;
    }

    /** @param array<int, mixed> $docs */
    public function deleteFiles(array $docs): void
    {
        foreach ($docs as $doc) {
            $this->files->delete($this->pathOf($doc), self::DOCUMENTS_DIR);
        }
    }

    private function pathOf(mixed $doc): ?string
    {
        $path = is_array($doc) ? ($doc['path'] ?? null) : $doc;

        return is_string($path) ? $path : null;
    }

    private function nameOf(mixed $doc): string
    {
        return basename(str_replace('\\', '/', (string) $this->pathOf($doc)));
    }

    private function hash(mixed $doc): ?string
    {
        $name = $this->nameOf($doc);
        $full = public_path(self::DOCUMENTS_DIR . '/' . $name);

        return $name !== '' && is_file($full) ? (hash_file(self::HASH_ALGO, $full) ?: null) : null;
    }

    /** @param UploadedFile[] $uploads @return array<string, true> */
    private function uploadHashes(array $uploads): array
    {
        $out = [];
        foreach ($uploads as $file) {
            $hash = hash_file(self::HASH_ALGO, $file->getRealPath());
            if ($hash !== false) {
                $out[$hash] = true;
            }
        }

        return $out;
    }
}
