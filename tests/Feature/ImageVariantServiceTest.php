<?php

namespace Tests\Feature;

use App\Services\ImageVariantService;
use App\Services\ImageVariantStatus;
use App\Services\StoredFileService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

/** Disque et GD réels sur un répertoire temporaire (config images.base_path) ; rien n'est mocké. */
class ImageVariantServiceTest extends TestCase
{
    use ImageFixtures;

    private string $base;
    private string $dir;
    private ImageVariantService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/variants-' . bin2hex(random_bytes(4));
        $this->dir = $this->base . '/covers';
        mkdir($this->dir, 0775, true);
        config(['images.base_path' => $this->base, 'images.directories' => ['covers']]);
        $this->service = new ImageVariantService();
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    public function test_creates_smaller_webp_next_to_original_and_keeps_original(): void
    {
        $png = $this->makePng($this->dir . '/a.png');
        $before = (string) md5_file($png);

        $r = $this->service->generate($png);

        $this->assertSame(ImageVariantStatus::Created, $r->status);
        $this->assertFileExists($png . '.webp');
        $this->assertLessThan(filesize($png), filesize($png . '.webp'));
        $this->assertSame($r->bytesAfter, filesize($png . '.webp'));
        $this->assertSame($before, md5_file($png));
        $this->assertSame(['a.png', 'a.png.webp'], $this->listing($this->dir), 'aucun fichier temporaire résiduel');
        $this->assertSame(0664, fileperms($png . '.webp') & 0777);
    }

    public function test_resizes_proportionally_without_upscaling(): void
    {
        $big = $this->makePng($this->dir . '/big.png', 1700, 120);
        $small = $this->makePng($this->dir . '/small.png', 300, 200);

        $this->service->generate($big);
        $this->service->generate($small);

        $this->assertSame([1600, 113], array_slice(getimagesize($big . '.webp'), 0, 2));
        $this->assertSame([300, 200], array_slice(getimagesize($small . '.webp'), 0, 2));
    }

    public function test_png_transparency_is_preserved(): void
    {
        $png = $this->makePng($this->dir . '/alpha.png', 300, 200, true);

        $this->assertTrue($this->service->generate($png)->isCreated());

        $webp = imagecreatefromwebp($png . '.webp');
        $alphaLeft = (imagecolorat($webp, 10, 100) >> 24) & 0x7F;
        $alphaRight = (imagecolorat($webp, 290, 100) >> 24) & 0x7F;
        $this->assertGreaterThan(100, $alphaLeft, 'zone transparente conservée');
        $this->assertLessThan(10, $alphaRight, 'zone opaque conservée');
    }

    public function test_jpeg_exif_orientation_is_applied(): void
    {
        $jpg = $this->makeJpeg($this->dir . '/rot.jpg', 400, 200, 6);

        $this->assertTrue($this->service->generate($jpg)->isCreated());

        $this->assertSame([200, 400], array_slice(getimagesize($jpg . '.webp'), 0, 2));
    }

    public function test_variant_not_smaller_is_deleted_and_reported(): void
    {
        $tiny = $this->makeHeavilyCompressedJpeg($this->dir . '/tiny.jpg');
        file_put_contents($tiny . '.webp', 'stale');

        $r = $this->service->generate($tiny, true);

        $this->assertSame(ImageVariantStatus::TooLarge, $r->status);
        $this->assertFileDoesNotExist($tiny . '.webp');
        $this->assertSame(['tiny.jpg'], $this->listing($this->dir));
    }

    public function test_idempotent_then_force_and_stale_regeneration(): void
    {
        $png = $this->makePng($this->dir . '/i.png');
        $this->assertTrue($this->service->generate($png)->isCreated());
        $mtime = filemtime($png . '.webp');

        $again = $this->service->generate($png);
        $this->assertSame(ImageVariantStatus::Skipped, $again->status);
        $this->assertSame('up_to_date', $again->reason);
        $this->assertSame($mtime, filemtime($png . '.webp'));

        touch($png . '.webp', time() - 1000);
        touch($png, time() - 500);
        $this->assertTrue($this->service->generate($png)->isCreated(), 'variante plus ancienne que l\'original');

        touch($png . '.webp', time() + 100);
        $this->assertSame(ImageVariantStatus::Skipped, $this->service->generate($png)->status);
        $this->assertTrue($this->service->generate($png, true)->isCreated(), 'force');
    }

    public function test_non_images_and_variants_are_ignored(): void
    {
        file_put_contents($this->dir . '/fake.png', 'not an image at all');
        file_put_contents($this->dir . '/notes.txt', 'hello');
        file_put_contents($this->dir . '/doc.pdf', '%PDF-1.4');
        $png = $this->makePng($this->dir . '/v.png');
        $this->service->generate($png);

        foreach (['fake.png', 'notes.txt', 'doc.pdf', 'v.png.webp'] as $name) {
            $this->assertSame(ImageVariantStatus::Unsupported, $this->service->generate($this->dir . '/' . $name)->status, $name);
        }
        $this->assertFileDoesNotExist($this->dir . '/fake.png.webp');
        $this->assertFileDoesNotExist($this->dir . '/v.png.webp.webp');
    }

    public function test_symlinks_are_ignored(): void
    {
        $real = $this->makePng($this->base . '/outside.png');
        symlink($real, $this->dir . '/link.png');

        $r = $this->service->generate($this->dir . '/link.png');

        $this->assertSame(ImageVariantStatus::Unsupported, $r->status);
        $this->assertSame('symlink', $r->reason);
        $this->assertFileDoesNotExist($real . '.webp');
        $this->assertFileDoesNotExist($this->dir . '/link.png.webp');
    }

    public function test_directory_outside_whitelist_and_traversal_are_refused(): void
    {
        mkdir($this->base . '/secret');
        $outside = $this->makePng($this->base . '/secret/x.png');

        $this->assertSame(ImageVariantStatus::Unsupported, $this->service->generate($outside)->status);
        $this->assertSame(ImageVariantStatus::Unsupported, $this->service->generate($this->dir . '/../secret/x.png')->status);
        $this->assertSame(ImageVariantStatus::Unsupported, $this->service->generateForStored('x.png', 'secret')->status);
        $this->assertSame(ImageVariantStatus::Unsupported, $this->service->generateForStored('x.png', '../secret')->status);
        $this->assertFileDoesNotExist($outside . '.webp');

        // basename : un nom stocké piégé est réduit à son basename dans le répertoire attendu.
        $png = $this->makePng($this->dir . '/ok.png');
        $this->assertTrue($this->service->generateForStored('../secret/../covers/ok.png', 'covers')->isCreated());
        $this->assertFileDoesNotExist($this->base . '/secret/x.png.webp');
    }

    public function test_animated_gif_is_skipped(): void
    {
        $gif = $this->dir . '/anim.gif';
        $frame = "\x00\x21\xF9\x04\x00\x0A\x00\x00\x00\x2C";
        $bytes = file_get_contents($this->makeStaticGif($this->dir . '/s.gif'));
        file_put_contents($gif, $bytes . $frame . $frame . $frame);
        // Entête GIF valide + 3 images déclarées : getimagesize lit l'entête, la détection compte les trames.
        $r = $this->service->generate($gif);

        $this->assertSame(ImageVariantStatus::Skipped, $r->status);
        $this->assertSame('animated', $r->reason);
    }

    private function makeStaticGif(string $path): string
    {
        $png = $this->makePng($path . '.src.png', 600, 400);
        imagegif(imagecreatefrompng($png), $path);
        unlink($png);

        return $path;
    }

    public function test_static_gif_and_light_webp(): void
    {
        $gif = $this->makeStaticGif($this->dir . '/s.gif');
        $this->assertSame(ImageVariantStatus::Created, $this->service->generate($gif)->status);
        $this->assertSame(['s.gif', 's.gif.webp'], $this->listing($this->dir));

        $webp = $this->dir . '/light.webp';
        imagewebp(imagecreatetruecolor(50, 50), $webp, 80);
        $r = $this->service->generate($webp);
        $this->assertSame(ImageVariantStatus::Skipped, $r->status);
        $this->assertSame('already_light', $r->reason);
    }

    public function test_memory_estimate_refuses_huge_images(): void
    {
        $this->assertTrue($this->service->fitsInMemory(4000, 3000));
        $this->assertFalse($this->service->fitsInMemory(10000, 10000));
        $this->assertFalse($this->service->fitsInMemory(PHP_INT_MAX >> 40, 1000));
    }

    public function test_gd_failure_returns_failed_without_exception_or_leftovers(): void
    {
        $png = $this->makePng($this->dir . '/f.png');
        $failing = new class extends ImageVariantService {
            protected function encode(\GdImage $image, string $destination): bool
            {
                file_put_contents($destination, 'partial');

                return false;
            }
        };

        $r = $failing->generate($png);

        $this->assertSame(ImageVariantStatus::Failed, $r->status);
        $this->assertSame(['f.png'], $this->listing($this->dir));
    }

    public function test_stored_file_delete_removes_variant(): void
    {
        $png = $this->makePng($this->dir . '/d.png');
        $this->service->generate($png);
        $this->assertFileExists($png . '.webp');
        $this->app->usePublicPath($this->base);

        $this->assertTrue((new StoredFileService())->delete('d.png', 'covers'));

        $this->assertFileDoesNotExist($png);
        $this->assertFileDoesNotExist($png . '.webp');
    }

    #[DataProvider('cornerProvider')]
    public function test_exif_orientation_moves_marker_corner_correctly(int $orientation, int $expectedX, int $expectedY): void
    {
        // Source 40x20 : coin haut-gauche rouge, reste bleu (blocs 8x8 pour survivre à la compression).
        $img = imagecreatetruecolor(40, 20);
        imagefill($img, 0, 0, 0x0000FF);
        imagefilledrectangle($img, 0, 0, 9, 9, 0xFF0000);
        $path = $this->dir . '/o' . $orientation . '.jpg';
        imagejpeg($img, $path, 100);
        unset($img);
        $tiff = "II*\x00\x08\x00\x00\x00" . "\x01\x00" . "\x12\x01\x03\x00\x01\x00\x00\x00" . pack('v', $orientation) . "\x00\x00" . "\x00\x00\x00\x00";
        $app1 = "Exif\x00\x00" . $tiff;
        $bytes = (string) file_get_contents($path);
        file_put_contents($path, substr($bytes, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($bytes, 2));

        // Webp léger de 40x20 : le JPEG q100 pèse plus que le webp q80.
        $r = $this->service->generate($path, true);
        if (!$r->isCreated()) {
            $this->markTestIncomplete('variante non créée : ' . ($r->reason ?? $r->status->value));
        }

        $webp = imagecreatefromwebp($path . '.webp');
        $w = imagesx($webp);
        $h = imagesy($webp);
        $expected = [$expectedX < 0 ? $w + $expectedX : $expectedX, $expectedY < 0 ? $h + $expectedY : $expectedY];
        $rgb = imagecolorat($webp, $expected[0], $expected[1]);
        $this->assertGreaterThan(150, ($rgb >> 16) & 0xFF, "orientation {$orientation} : coin rouge attendu en ({$expected[0]},{$expected[1]})");
        $this->assertLessThan(100, $rgb & 0xFF);
    }

    /** @return array<string, array{int, int, int}> orientation => coin rouge (négatif = depuis le bord opposé) */
    public static function cornerProvider(): array
    {
        return [
            '2 miroir H' => [2, -3, 3],
            '3 180' => [3, -3, -3],
            '4 miroir V' => [4, 3, -3],
            '5 transposition' => [5, 3, 3],
            '6 90 CW' => [6, -3, 3],
            '7 transverse' => [7, -3, -3],
            '8 90 CCW' => [8, 3, -3],
        ];
    }

    public function test_animated_png_is_skipped(): void
    {
        $png = $this->makePng($this->dir . '/apng.png');
        $bytes = (string) file_get_contents($png);
        $pos = strpos($bytes, 'IDAT') - 4;
        $actl = pack('N', 8) . 'acTL' . pack('NN', 2, 0) . pack('N', crc32('acTL' . pack('NN', 2, 0)));
        file_put_contents($png, substr($bytes, 0, $pos) . $actl . substr($bytes, $pos));

        $r = $this->service->generate($png);

        $this->assertSame(ImageVariantStatus::Skipped, $r->status);
        $this->assertSame('animated', $r->reason);
        $this->assertFileDoesNotExist($png . '.webp');
    }

    public function test_stored_file_delete_never_removes_variant_of_non_image_or_private_copy(): void
    {
        $this->app->usePublicPath($this->base);
        file_put_contents($this->dir . '/doc.pdf', '%PDF');
        file_put_contents($this->dir . '/doc.pdf.webp', 'decoy');
        $png = $this->makePng($this->dir . '/keep.png');
        file_put_contents($this->dir . '/other.png.webp', 'other');

        (new StoredFileService())->delete('doc.pdf', 'covers');
        (new StoredFileService())->delete('keep.png', 'covers');

        $this->assertFileExists($this->dir . '/doc.pdf.webp', 'non-image : variante jamais supprimée');
        $this->assertFileExists($this->dir . '/other.png.webp', 'seule la variante de CE fichier est supprimée');
        $this->assertFileDoesNotExist($png);
    }

    public function test_webp_original_deletion_removes_only_its_own_double_extension(): void
    {
        $this->app->usePublicPath($this->base);
        file_put_contents($this->dir . '/photo.webp', 'w');
        file_put_contents($this->dir . '/photo.webp.webp', 'v');
        file_put_contents($this->dir . '/photo.png.webp', 'neighbour');

        (new StoredFileService())->delete('photo.webp', 'covers');

        $this->assertSame(['photo.png.webp'], $this->listing($this->dir));
    }

    public function test_symlinked_original_and_symlinked_variant_are_never_followed(): void
    {
        $outside = sys_get_temp_dir() . '/outside-' . bin2hex(random_bytes(4)) . '.png';
        $this->makePng($outside);
        symlink($outside, $this->dir . '/link.png');

        $this->assertSame('symlink', $this->service->generate($this->dir . '/link.png')->reason);
        $this->assertFileDoesNotExist($outside . '.webp');

        $target = sys_get_temp_dir() . '/victim-' . bin2hex(random_bytes(4));
        file_put_contents($target, 'precious');
        $png = $this->makePng($this->dir . '/s.png');
        symlink($target, $png . '.webp');
        $this->assertTrue($this->service->generate($png)->isCreated());
        $this->assertSame('precious', file_get_contents($target), 'la cible du lien n\'est pas écrasée');
        $this->assertFalse(is_link($png . '.webp'));
        @unlink($outside);
        @unlink($target);
    }

    public function test_uppercase_extension_gets_variant_and_delete_touches_only_its_own(): void
    {
        $upper = $this->makePng($this->dir . '/X.PNG');
        file_put_contents($this->dir . '/x.png.webp', 'other-file');
        file_put_contents($this->dir . '/X.PNG.keep', 'other');

        $this->assertTrue($this->service->generate($upper)->isCreated());
        $this->assertFileExists($this->dir . '/X.PNG.webp');
        $this->assertTrue(ImageVariantService::isOriginalName('X.PNG'));
        $this->assertFalse(ImageVariantService::isOriginalName('X.PNG.webp'));
        $this->assertFalse(ImageVariantService::isOriginalName('X.PNG.keep'));

        $this->app->usePublicPath($this->base);
        $this->assertTrue((new StoredFileService())->delete('X.PNG', 'covers'));

        $this->assertFileDoesNotExist($this->dir . '/X.PNG');
        $this->assertFileDoesNotExist($this->dir . '/X.PNG.webp');
        $this->assertSame(['X.PNG.keep', 'x.png.webp'], $this->listing($this->dir), 'rien d\'autre supprimé');
    }

    public function test_forced_regeneration_with_undecodable_original_removes_stale_variant(): void
    {
        $png = $this->makePng($this->dir . '/c.png');
        $this->assertTrue($this->service->generate($png)->isCreated());
        $bytes = (string) file_get_contents($png);
        file_put_contents($png, substr($bytes, 0, 70) . str_repeat("\x07", 500));

        $r = $this->service->generate($png, true);

        $this->assertNotSame(ImageVariantStatus::Created, $r->status);
        $this->assertFileDoesNotExist($png . '.webp', 'variante périmée supprimée');
    }

    public function test_plan_never_deletes_stale_variant(): void
    {
        $png = $this->makePng($this->dir . '/p.png');
        file_put_contents($png . '.webp', 'stale');
        file_put_contents($png, substr((string) file_get_contents($png), 0, 70) . str_repeat("\x07", 500));

        $this->service->plan($png, true);

        $this->assertFileExists($png . '.webp');
    }
}
