<?php

namespace Tests\Support;

/** Générateurs d'images de test (GD réel, aucun mock). */
trait ImageFixtures
{
    /** PNG "photo-like" (dégradé + bruit déterministe) : nettement plus lourd en PNG qu'en WebP. */
    private function makePng(string $path, int $width = 600, int $height = 400, bool $alpha = false): string
    {
        $img = imagecreatetruecolor($width, $height);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        mt_srand(42);
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $noise = mt_rand(0, 40);
                $transparent = $alpha && $x < $width / 2 ? 127 : 0;
                imagesetpixel($img, $x, $y, ($transparent << 24) | (((intdiv($x * 255, $width) + $noise) % 256) << 16) | (((intdiv($y * 255, $height) + $noise) % 256) << 8) | (($x + $y + $noise) % 256));
            }
        }
        imagepng($img, $path, 0);
        unset($img);

        return $path;
    }

    private function makeJpeg(string $path, int $width, int $height, ?int $orientation = null): string
    {
        $img = imagecreatetruecolor($width, $height);
        mt_srand(7);
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                imagesetpixel($img, $x, $y, (mt_rand(0, 255) << 16) | ((($x * 2) % 256) << 8) | (($y * 2) % 256));
            }
        }
        imagejpeg($img, $path, 100);
        unset($img);

        if ($orientation !== null) {
            // APP1 Exif minimal (TIFF little-endian, un tag Orientation) inséré après SOI.
            $tiff = "II*\x00\x08\x00\x00\x00" . "\x01\x00" . "\x12\x01\x03\x00\x01\x00\x00\x00" . pack('v', $orientation) . "\x00\x00" . "\x00\x00\x00\x00";
            $app1 = "Exif\x00\x00" . $tiff;
            $segment = "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1;
            $bytes = (string) file_get_contents($path);
            file_put_contents($path, substr($bytes, 0, 2) . $segment . substr($bytes, 2));
        }

        return $path;
    }

    /** JPEG très compressé (qualité 1) : sa variante WebP q80 est plus lourde que lui. */
    private function makeHeavilyCompressedJpeg(string $path): string
    {
        $img = imagecreatetruecolor(300, 300);
        mt_srand(1);
        for ($x = 0; $x < 300; $x++) {
            for ($y = 0; $y < 300; $y++) {
                imagesetpixel($img, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }
        imagejpeg($img, $path, 1);

        return $path;
    }

    /** @return string[] noms triés du dossier (garde de listing avant/après) */
    private function listing(string $dir): array
    {
        return is_dir($dir) ? array_values(array_diff(scandir($dir) ?: [], ['.', '..'])) : [];
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
            $p = $dir . '/' . $entry;
            is_dir($p) && !is_link($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
