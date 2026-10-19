<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private const HSTS_VALUE = 'max-age=31536000; includeSubDomains';

    private const IMAGE_CACHE_MAX_AGE = 'max-age=604800';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/security-headers/cover.jpg', static fn () => response('binary', 200, [
            'Content-Type' => 'image/jpeg',
        ]));

        Route::get('/security-headers/page.jpg', static fn () => response('<html></html>', 200, [
            'Content-Type' => 'text/html',
        ]));

        Route::get('/security-headers/data.jpg', static fn () => response()->json(['ok' => true]));
    }

    public function test_api_json_response_is_not_publicly_cached(): void
    {
        Book::factory()->create(['status' => 1, 'slug' => 'livre-sans-cache']);

        $response = $this->getJson('/api/books/livre-sans-cache');

        $response->assertOk();
        $this->assertStringNotContainsString(
            'public',
            strtolower((string) $response->headers->get('Cache-Control'))
        );
    }

    public function test_json_response_with_image_extension_is_not_publicly_cached(): void
    {
        $response = $this->getJson('/security-headers/data.jpg');

        $response->assertOk();
        $this->assertStringNotContainsString(
            'public',
            strtolower((string) $response->headers->get('Cache-Control'))
        );
    }

    public function test_hsts_is_set_on_https_request(): void
    {
        $response = $this->withHeader('X-Forwarded-Proto', 'https')
            ->getJson('/security-headers/data.jpg');

        $response->assertOk()->assertHeader('Strict-Transport-Security', self::HSTS_VALUE);
    }

    public function test_hsts_is_absent_on_http_request(): void
    {
        $response = $this->getJson('/security-headers/data.jpg');

        $response->assertOk();
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_image_response_is_cached_for_seven_days(): void
    {
        $response = $this->get('/security-headers/cover.jpg');

        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // Symfony trie les directives cache-control par ordre alphabétique.
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString(self::IMAGE_CACHE_MAX_AGE, $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
    }

    public function test_html_response_with_image_extension_is_not_cached(): void
    {
        $response = $this->get('/security-headers/page.jpg');

        $response->assertOk();
        $this->assertStringNotContainsString(
            'immutable',
            strtolower((string) $response->headers->get('Cache-Control'))
        );
    }

    public function test_security_headers_are_still_applied(): void
    {
        $response = $this->getJson('/security-headers/data.jpg');

        $response
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
