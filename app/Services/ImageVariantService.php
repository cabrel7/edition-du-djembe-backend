<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Variantes WebP légères des images publiques : `<fichier original>.webp` à côté de l'original.
 *
 * L'original n'est JAMAIS modifié. Aucune exception ne remonte à l'appelant (warning loggué).
 * Garde-fous : liste blanche de répertoires (config/images.php), fichiers directs uniquement
 * (ni symlink, ni sous-dossier), type réel par magic bytes, plafond mémoire, écriture atomique,
 * variante supprimée si elle n'est pas plus légère que l'original.
 */
class ImageVariantService
{
    public const MAX_DIMENSION = 1600;
    public const WEBP_QUALITY = 80;
    public const VARIANT_SUFFIX = '.webp';
    public const FILE_MODE = 0664;

    /** Mémoire GD estimée (largeur x hauteur x 4) au-delà de laquelle l'image est ignorée. */
    public const MAX_SOURCE_MEMORY_BYTES = 96 * 1024 * 1024;
    /** Marge pour la copie redimensionnée, l'encodeur et le runtime PHP. */
    private const MEMORY_OVERHEAD_BYTES = 16 * 1024 * 1024;
    private const BYTES_PER_PIXEL = 4;
    /** Un webp original de moins de cette taille (et dans la limite de dimension) est considéré déjà léger. */
    public const LIGHT_WEBP_BYTES = 200 * 1024;

    private const EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    private const SUPPORTED_TYPES = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    private const VARIANT_NAME_PATTERN = '/\.(?:png|jpe?g|gif|webp)\.webp$/i';
    /** Nom exact des fichiers temporaires d'écriture : `<original>.webp.<12 hex>.tmp`. */
    private const TEMP_NAME_PATTERN = '/\.(?:png|jpe?g|gif|webp)\.webp\.[0-9a-f]{12}\.tmp$/i';
    /** Un temporaire plus ancien que ceci est orphelin (génération interrompue). */
    public const STALE_TEMP_SECONDS = 3600;
    private const PNG_HEAD_BYTES = 4096;
    private const ANIMATED_GIF_FRAME_PATTERN = '#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s';

    /** Vrai si le NOM est celui d'une image source supportée (et non d'une variante `.<ext>.webp`). */
    public static function isOriginalName(string $name): bool
    {
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::EXTENSIONS, true)
            && preg_match(self::VARIANT_NAME_PATTERN, $name) !== 1;
    }

    /**
     * Temporaires orphelins (motif exact + plus vieux que STALE_TEMP_SECONDS) directement dans $directory.
     * Aucun autre fichier n'est jamais retourné.
     *
     * @return string[] chemins absolus
     */
    public function staleTempFiles(string $directory): array
    {
        $found = [];
        foreach (is_dir($directory) ? (scandir($directory) ?: []) : [] as $name) {
            $path = $directory . '/' . $name;
            if (preg_match(self::TEMP_NAME_PATTERN, $name) === 1 && !is_link($path) && is_file($path)
                && time() - (int) filemtime($path) > self::STALE_TEMP_SECONDS) {
                $found[] = $path;
            }
        }

        return $found;
    }

    public static function variantPath(string $original): string
    {
        return $original . self::VARIANT_SUFFIX;
    }

    /** @return string[] répertoires autorisés (relatifs) */
    public function allowedDirectories(): array
    {
        return array_values((array) config('images.directories', []));
    }

    public function baseDirectory(): string
    {
        return rtrim((string) (config('images.base_path') ?: public_path()), '/');
    }

    /**
     * Fichier fraîchement stocké : basename + répertoire attendu fournis par le code (jamais le client).
     */
    public function generateForStored(?string $stored, string $directory, bool $force = false): ImageVariantResult
    {
        $name = $stored === null ? '' : basename(str_replace('\\', '/', $stored));
        if ($name === '' || $name === '.' || $name === '..' || !in_array($directory, $this->allowedDirectories(), true)) {
            return $this->result(ImageVariantStatus::Unsupported, (string) $stored, reason: 'outside_whitelist');
        }

        return $this->generate($this->baseDirectory() . '/' . $directory . '/' . $name, $force);
    }

    /** Génère la variante ; ne lève jamais d'exception. */
    public function generate(string $path, bool $force = false): ImageVariantResult
    {
        try {
            $info = $this->inspect($path, $force);
            if ($info instanceof ImageVariantResult) {
                // Original illisible en --force : on ne laisse pas servir une variante périmée.
                if ($force && $info->reason === 'not_an_image' && self::isOriginalName(basename($path))) {
                    $this->removeVariant($path);
                }

                return $info;
            }

            return $this->process($path, $info);
        } catch (\Throwable $e) {
            Log::warning('Variante WebP en échec', ['file' => basename($path), 'error' => $e->getMessage()]);

            return $this->result(ImageVariantStatus::Failed, $path, reason: 'exception');
        }
    }

    /**
     * Simulation : null = la génération serait tentée ; sinon la raison pour laquelle elle est ignorée.
     */
    public function plan(string $path, bool $force = false): ?ImageVariantResult
    {
        try {
            $info = $this->inspect($path, $force);

            return $info instanceof ImageVariantResult ? $info : null;
        } catch (\Throwable $e) {
            return $this->result(ImageVariantStatus::Failed, $path, reason: 'exception');
        }
    }

    /**
     * @return ImageVariantResult|array{width: int, height: int, type: int, size: int}
     */
    private function inspect(string $path, bool $force): ImageVariantResult|array
    {
        if (is_link($path)) {
            return $this->result(ImageVariantStatus::Unsupported, $path, reason: 'symlink');
        }
        if (!$this->isDirectlyInAllowedDirectory($path)) {
            return $this->result(ImageVariantStatus::Unsupported, $path, reason: 'outside_whitelist');
        }

        if (!self::isOriginalName(basename($path))) {
            return $this->result(ImageVariantStatus::Unsupported, $path, reason: 'not_an_image');
        }

        $size = (int) filesize($path);
        $variant = self::variantPath($path);
        if (!$force && $this->isUpToDate($path, $variant)) {
            return $this->result(ImageVariantStatus::Skipped, $path, $size, (int) filesize($variant), 'up_to_date');
        }

        $meta = @getimagesize($path);
        if ($meta === false || !in_array($meta[2], self::SUPPORTED_TYPES, true) || $meta[0] < 1 || $meta[1] < 1) {
            return $this->result(ImageVariantStatus::Unsupported, $path, $size, reason: 'not_an_image');
        }
        [$width, $height, $type] = [(int) $meta[0], (int) $meta[1], (int) $meta[2]];

        if ($this->isAnimated($path, $type)) {
            return $this->result(ImageVariantStatus::Skipped, $path, $size, reason: 'animated');
        }
        if ($type === IMAGETYPE_WEBP && $size <= self::LIGHT_WEBP_BYTES && max($width, $height) <= self::MAX_DIMENSION) {
            return $this->result(ImageVariantStatus::Skipped, $path, $size, reason: 'already_light');
        }
        if (!$this->fitsInMemory($width, $height)) {
            return $this->result(ImageVariantStatus::Skipped, $path, $size, reason: 'memory_limit');
        }

        return ['width' => $width, 'height' => $height, 'type' => $type, 'size' => $size];
    }

    /** @param array{width: int, height: int, type: int, size: int} $info */
    private function process(string $path, array $info): ImageVariantResult
    {
        $variant = self::variantPath($path);
        $temp = $variant . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $image = null;

        try {
            $image = $this->load($path, $info['type']);
            if ($image === false) {
                // Décodage impossible : la variante existante serait périmée, on la retire (--force).
                if (extension_loaded('gd') && function_exists('imagewebp')) {
                    $this->removeVariant($path);
                }

                return $this->result(ImageVariantStatus::Failed, $path, $info['size'], reason: 'decode');
            }
            $image = $this->prepare($image, $info['width'], $info['height']);
            if ($info['type'] === IMAGETYPE_JPEG) {
                $image = $this->applyOrientation($image, $this->exifOrientation($path));
            }

            if (!$this->encode($image, $temp)) {
                return $this->result(ImageVariantStatus::Failed, $path, $info['size'], reason: 'encode');
            }
            $image = null; // libère la mémoire GD avant les opérations disque

            clearstatcache(true, $temp);
            $after = (int) filesize($temp);
            if ($after <= 0 || $after >= $info['size']) {
                @unlink($variant);

                return $this->result(ImageVariantStatus::TooLarge, $path, $info['size'], $after, 'not_smaller');
            }

            @chmod($temp, self::FILE_MODE);
            if (!@rename($temp, $variant)) {
                return $this->result(ImageVariantStatus::Failed, $path, $info['size'], reason: 'rename');
            }

            return $this->result(ImageVariantStatus::Created, $path, $info['size'], $after);
        } finally {
            $image = null;
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    private function removeVariant(string $original): void
    {
        $variant = self::variantPath($original);
        if (is_file($variant) || is_link($variant)) {
            @unlink($variant);
        }
    }

    /** Point d'extension (tests d'échec GD). */
    protected function encode(\GdImage $image, string $destination): bool
    {
        return $this->guarded(static fn () => imagewebp($image, $destination, self::WEBP_QUALITY));
    }

    private function load(string $path, int $type): \GdImage|false
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            return false;
        }

        return $this->guarded(static fn () => match ($type) {
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        }) ?: false;
    }

    /** Truecolor + alpha, redimensionnée sans agrandissement (proportions conservées). */
    private function prepare(\GdImage $image, int $width, int $height): \GdImage
    {
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $longest = max($width, $height);
        if ($longest <= self::MAX_DIMENSION) {
            return $image;
        }

        $ratio = self::MAX_DIMENSION / $longest;
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, (int) imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private function exifOrientation(string $path): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }
        $exif = $this->guarded(static fn () => exif_read_data($path, 'IFD0'));

        return is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
    }

    /** Orientation EXIF appliquée sur l'image déjà réduite (peu de mémoire). */
    private function applyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        // imagerotate : angle anti-horaire.
        $rotate = [3 => 180, 4 => 180, 5 => 90, 6 => 270, 7 => 270, 8 => 90][$orientation] ?? 0;
        $flip = in_array($orientation, [2, 4, 5, 7], true);

        // Miroir AVANT rotation : EXIF 5 = miroir horizontal puis rotation 270 CW, 7 = miroir puis 90 CW.
        if ($flip) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($rotate !== 0) {
            $rotated = imagerotate($image, (float) $rotate, 0);
            if ($rotated !== false) {
                imagealphablending($rotated, false);
                imagesavealpha($rotated, true);
                $image = $rotated;
            }
        }

        return $image;
    }

    private function isDirectlyInAllowedDirectory(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }
        $parent = realpath(dirname($path));
        if ($parent === false) {
            return false;
        }
        foreach ($this->allowedDirectories() as $directory) {
            $allowed = realpath($this->baseDirectory() . '/' . $directory);
            if ($allowed !== false && $allowed === $parent) {
                return true;
            }
        }

        return false;
    }

    private function isUpToDate(string $original, string $variant): bool
    {
        if (is_link($variant) || !is_file($variant)) {
            return false;
        }
        clearstatcache(true, $original);
        clearstatcache(true, $variant);

        return filemtime($variant) >= filemtime($original);
    }

    private function isAnimated(string $path, int $type): bool
    {
        if ($type !== IMAGETYPE_GIF && $type !== IMAGETYPE_WEBP && $type !== IMAGETYPE_PNG) {
            return false;
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        try {
            if ($type === IMAGETYPE_PNG) {
                // APNG : le chunk acTL précède toujours le premier IDAT ; une variante statique perdrait l'animation.
                $head = (string) fread($handle, self::PNG_HEAD_BYTES);
                $actl = strpos($head, 'acTL');
                $idat = strpos($head, 'IDAT');

                return $actl !== false && ($idat === false || $actl < $idat);
            }
            if ($type === IMAGETYPE_WEBP) {
                // VP8X (octet 20) : bit 0x02 = animation.
                $head = (string) fread($handle, 30);

                return substr($head, 12, 4) === 'VP8X' && (ord($head[20] ?? "\0") & 0x02) !== 0;
            }

            $frames = preg_match_all(self::ANIMATED_GIF_FRAME_PATTERN, (string) stream_get_contents($handle));

            // Échec PCRE (false) : on suppose animé (repli prudent, l'original reste servi).
            return $frames === false || $frames > 1;
        } finally {
            fclose($handle);
        }
    }

    /** Mémoire GD estimée (source + copie réduite + marge) vs plafond constant et memory_limit PHP. */
    public function fitsInMemory(int $width, int $height): bool
    {
        $source = $width * $height * self::BYTES_PER_PIXEL;
        if ($source > self::MAX_SOURCE_MEMORY_BYTES) {
            return false;
        }

        $longest = max($width, $height);
        $ratio = $longest > self::MAX_DIMENSION ? self::MAX_DIMENSION / $longest : 1.0;
        $resized = (int) ceil($width * $ratio) * (int) ceil($height * $ratio) * self::BYTES_PER_PIXEL;
        $needed = $source + ($ratio < 1.0 ? $resized : 0) + self::MEMORY_OVERHEAD_BYTES;

        $limit = $this->memoryLimitBytes();

        return $limit < 0 || $needed <= $limit - memory_get_usage(true);
    }

    private function memoryLimitBytes(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return -1;
        }
        $value = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Exécute un appel GD/EXIF bruyant : les warnings deviennent des exceptions locales (jamais fatales).
     *
     * @template T
     * @param callable(): T $call
     * @return T|false
     */
    private function guarded(callable $call): mixed
    {
        set_error_handler(static function (int $no, string $message): never {
            throw new \ErrorException($message, 0, $no);
        });
        try {
            return $call();
        } catch (\Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    private function result(ImageVariantStatus $status, string $source, int $before = 0, int $after = 0, ?string $reason = null): ImageVariantResult
    {
        if ($status === ImageVariantStatus::Failed) {
            Log::warning('Variante WebP non générée', ['file' => basename($source), 'reason' => $reason]);
        }

        return new ImageVariantResult($status, $source, $before, $after, $reason);
    }
}
