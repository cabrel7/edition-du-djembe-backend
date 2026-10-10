<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_returns_token(): void
    {
        $user = User::factory()->create(['email' => 'mika@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'mika@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['user', 'token']);
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

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Nouvel',
            'email' => 'nouvel@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['user', 'token']);
        $this->assertDatabaseHas('users', ['email' => 'nouvel@example.com']);
    }

    public function test_register_rejects_duplicate_email_and_weak_password(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/register', [
            'name' => 'X',
            'email' => 'dup@example.com',
            'password' => 'secret123',
        ])->assertStatus(422);

        $this->postJson('/api/register', [
            'name' => 'X',
            'email' => 'ok@example.com',
            'password' => 'court',
        ])->assertStatus(422);
    }

    public function test_logout_succeeds_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $plainToken = $user->createToken('test')->accessToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $plainToken])
            ->postJson('/api/logout')
            ->assertStatus(200);
    }

    public function test_logout_requires_auth(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }
}
