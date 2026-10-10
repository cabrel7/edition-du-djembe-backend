<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_send_contact(): void
    {
        $response = $this->postJson('/api/contacts', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question catalogue',
            'message' => 'Bonjour, ce livre est-il encore disponible ?',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('contacts', ['email' => 'jean@example.com', 'status' => 'pending']);
    }

    public function test_contact_requires_valid_fields(): void
    {
        $this->postJson('/api/contacts', ['name' => 'X'])->assertStatus(422);
    }

    public function test_contact_index_requires_auth(): void
    {
        $this->getJson('/api/contacts')->assertStatus(401);
    }

    public function test_admin_sees_contacts(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 1]));
        Contact::factory()->count(2)->create();

        $this->getJson('/api/contacts')->assertStatus(200);
    }
}
