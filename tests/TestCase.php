<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Client;
use Laravel\Passport\PersonalAccessClient;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePassportPersonalAccessClient();
    }

    private function ensurePassportPersonalAccessClient(): void
    {
        if (!Schema::hasTable('oauth_personal_access_clients')) {
            return;
        }

        if (PersonalAccessClient::query()->count() > 0) {
            return;
        }

        $client = Client::create([
            'user_id' => null,
            'name' => 'Passport Testing Personal Access Client',
            'secret' => 'testing-secret',
            'redirect' => 'http://localhost',
            'personal_access_client' => true,
            'password_client' => false,
            'revoked' => false,
        ]);

        PersonalAccessClient::create([
            'client_id' => $client->id,
        ]);
    }
}
