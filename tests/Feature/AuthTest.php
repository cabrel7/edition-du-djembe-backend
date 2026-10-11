<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function responseCookie($response, string $name): ?string
    {
        $cookie = $response->getCookie($name, false);

        return $cookie instanceof \Symfony\Component\HttpFoundation\Cookie
            ? $cookie->getValue()
            : null;
    }

    public function test_login_success_sets_httponly_cookie_and_hides_token(): void
    {
        $user = User::factory()->create(['email' => 'mika@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'mika@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['user']);

        $cookie = $this->responseCookie($response, 'edj_token');
        $this->assertNotNull($cookie, 'Le cookie d\'authentification doit être posé');
        $this->assertNotEmpty($cookie);

        $setCookie = (string) $response->baseResponse->headers->get('Set-Cookie');
        $this->assertStringContainsString('edj_token=' . $cookie, $setCookie);
        $this->assertStringContainsString('httponly', strtolower($setCookie), 'Le cookie doit être httpOnly (inaccessible au JS)');
        $this->assertStringNotContainsString('token', $response->getContent() === '' ? '' : json_encode($response->json()));

        // Le token ne doit JAMAIS apparaître dans le corps JSON (faille localStorage).
        $this->assertArrayNotHasKey('token', $response->json());
    }

    public function test_login_wrong_password_is_401(): void
    {
        User::factory()->create(['email' => 'mika@example.com']);

        $this->postJson('/api/login', [
            'email' => 'mika@example.com',
            'password' => 'mauvais',
        ])->assertStatus(401);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])->assertStatus(422);
    }

    public function test_register_route_is_removed(): void
    {
        // L'inscription publique a été retirée (création de comptes réservée à l'admin).
        $this->postJson('/api/register', [
            'name' => 'Nouvel',
            'email' => 'nouvel@example.com',
            'password' => 'secret123',
        ])->assertStatus(404);

        $this->assertDatabaseMissing('users', ['email' => 'nouvel@example.com']);
    }

    public function test_logout_succeeds_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $plainToken = $user->createToken('test')->accessToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $plainToken])
            ->postJson('/api/logout')
            ->assertStatus(200);
    }

    public function test_login_cookie_authenticates_api_requests_without_header(): void
    {
        $user = User::factory()->create(['email' => 'cookie@example.com']);

        $login = $this->postJson('/api/login', [
            'email' => 'cookie@example.com',
            'password' => 'password',
        ]);
        $login->assertStatus(200);
        $cookie = $this->responseCookie($login, 'edj_token');
        $this->assertNotNull($cookie, 'Le cookie httpOnly doit être posé au login');

        // Requête API uniquement avec le cookie (aucun header) : authentifiée.
        $this->withCredentials()->withUnencryptedCookies(['edj_token' => $cookie])
            ->postJson('/api/logout')
            ->assertStatus(200);
    }

    public function test_logout_requires_auth(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }
}
