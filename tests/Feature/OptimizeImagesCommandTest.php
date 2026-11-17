<?php

namespace Tests\Feature;

use Tests\Support\ImageFixtures;
use Tests\TestCase;

class OptimizeImagesCommandTest extends TestCase
{
    use ImageFixtures;

    private string $base;
    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/optimg-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/books/covers', 0775, true);
        mkdir($this->base . '/team', 0775, true);
        $this->lockFile = $this->base . '/test.lock';
        config([
            'images.base_path' => $this->base,
            'images.directories' => ['books/covers', 'team'],
            'images.lock_file' => $this->lockFile,
        ]);
        $this->makePng($this->base . '/books/covers/a.png');
        $this->makePng($this->base . '/books/covers/b.png');
        $this->makePng($this->base . '/team/c.png');
        file_put_contents($this->base . '/team/readme.txt', 'x');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    public function test_default_is_dry_run_and_writes_nothing(): void
    {
        $before = [$this->listing($this->base . '/books/covers'), $this->listing($this->base . '/team')];

        $this->artisan('app:optimize-images')
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);

        $this->assertSame($before, [$this->listing($this->base . '/books/covers'), $this->listing($this->base . '/team')]);
    }

    public function test_apply_creates_variants_and_is_idempotent(): void
    {
        $this->artisan('app:optimize-images', ['--apply' => true])->assertExitCode(0);

        foreach (['books/covers/a.png', 'books/covers/b.png', 'team/c.png'] as $f) {
            $this->assertFileExists($this->base . '/' . $f . '.webp');
        }
        $this->assertFileDoesNotExist($this->base . '/team/readme.txt.webp');
        $mtime = filemtime($this->base . '/team/c.png.webp');

        $this->artisan('app:optimize-images', ['--apply' => true])->assertExitCode(0);
        $this->assertSame($mtime, filemtime($this->base . '/team/c.png.webp'));
    }

    public function test_dir_and_limit_options(): void
    {
        $this->artisan('app:optimize-images', ['--apply' => true, '--dir' => 'team'])->assertExitCode(0);
        $this->assertFileExists($this->base . '/team/c.png.webp');
        $this->assertFileDoesNotExist($this->base . '/books/covers/a.png.webp');

        $this->artisan('app:optimize-images', ['--apply' => true, '--dir' => 'books/covers', '--limit' => 1])->assertExitCode(0);
        $created = count(glob($this->base . '/books/covers/*.webp'));
        $this->assertSame(1, $created);
    }

    public function test_dir_outside_whitelist_is_refused(): void
    {
        $this->artisan('app:optimize-images', ['--apply' => true, '--dir' => '../etc'])->assertExitCode(1);
        $this->artisan('app:optimize-images', ['--apply' => true, '--dry-run' => true])->assertExitCode(1);
    }

    public function test_concurrent_run_is_refused_by_lock(): void
    {
        $handle = fopen($this->lockFile, 'c');
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));

        $this->artisan('app:optimize-images', ['--apply' => true])
            ->expectsOutputToContain('déjà en cours')
            ->assertExitCode(1);

        flock($handle, LOCK_UN);
        fclose($handle);
        $this->assertSame([], glob($this->base . '/*/*.webp'));
        $this->artisan('app:optimize-images', ['--apply' => true])->assertExitCode(0);
    }

    public function test_symlinks_are_not_followed(): void
    {
        $outside = $this->makePng($this->base . '/outside.png');
        symlink($outside, $this->base . '/team/l.png');

        $this->artisan('app:optimize-images', ['--apply' => true])->assertExitCode(0);

        $this->assertFileDoesNotExist($outside . '.webp');
        $this->assertFileDoesNotExist($this->base . '/team/l.png.webp');
    }

    private function corruptPng(string $path): void
    {
        $this->makePng($path);
        file_put_contents($path, substr((string) file_get_contents($path), 0, 70) . str_repeat("\x07", 500));
    }

    public function test_failed_files_give_non_zero_exit_code(): void
    {
        $this->corruptPng($this->base . '/team/bad.png');

        $this->artisan('app:optimize-images', ['--apply' => true])->assertExitCode(1);

        $this->assertFileExists($this->base . '/team/c.png.webp', 'les autres fichiers sont traités malgré l\'échec');
    }

    public function test_exit_code_is_zero_without_failures(): void
    {
        $this->artisan('app:optimize-images', ['--apply' => true])->assertExitCode(0);
    }

    public function test_orphan_temp_files_older_than_one_hour_are_purged_only_when_applying(): void
    {
        $old = $this->base . '/team/c.png.webp.0123456789ab.tmp';
        $recent = $this->base . '/team/c.png.webp.ba9876543210.tmp';
        $lookalike = $this->base . '/team/notes.tmp';
        $notTemp = $this->base . '/team/c.png.webp.0123456789ab.tmp.bak';
        foreach ([$old, $recent, $lookalike, $notTemp] as $f) {
            file_put_contents($f, 'x');
            touch($f, time() - 7200);
        }
        touch($recent, time() - 60);

        $this->artisan('app:optimize-images')
            ->expectsOutputToContain('1 fichier(s) temporaire(s) orphelin(s) détecté(s)')
            ->assertExitCode(0);
        $this->assertFileExists($old, 'dry-run : listé seulement');

        $this->artisan('app:optimize-images', ['--apply' => true])
            ->expectsOutputToContain('1 fichier(s) temporaire(s) orphelin(s) supprimé(s)')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
        $this->assertFileExists($lookalike);
        $this->assertFileExists($notTemp);
    }

    public function test_unopenable_lock_file_is_an_explicit_error_not_a_busy_lock(): void
    {
        config(['images.lock_file' => $this->base . '/no-such-dir/l.lock']);

        $this->artisan('app:optimize-images', ['--apply' => true])
            ->expectsOutputToContain('Impossible d\'ouvrir le fichier de verrou')
            ->assertExitCode(1);
    }

    public function test_lock_file_is_created_group_readable(): void
    {
        $this->artisan('app:optimize-images', ['--apply' => true])->assertExitCode(0);

        $this->assertSame(0664, fileperms($this->lockFile) & 0777);
    }
}
