<?php

namespace Tests\Feature;

use App\Mail\SubmissionReceived;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'book',
            'first_name' => 'Amina',
            'last_name' => 'Diallo',
            'email' => 'amina@example.com',
            'title' => 'Un manuscrit passionnant',
            'summary' => str_repeat('Résumé suffisamment long pour la validation. ', 5),
            'file' => UploadedFile::fake()->create('manuscrit.pdf', 100, 'application/pdf'),
            'rgpd_consent' => '1',
        ], $overrides);
    }

    public function test_guest_can_submit_and_receives_acknowledgement_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/submissions', $this->validPayload());

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('submissions', [
            'email' => 'amina@example.com',
            'status' => 'pending',
        ]);
        Mail::assertSent(SubmissionReceived::class, function ($mail) {
            return $mail->hasTo('amina@example.com');
        });
    }

    public function test_submission_requires_pdf_and_rgpd(): void
    {
        $noFile = $this->validPayload();
        unset($noFile['file']);
        $this->postJson('/api/submissions', $noFile)->assertStatus(422);

        $noRgpd = $this->validPayload(['rgpd_consent' => null]);
        $this->postJson('/api/submissions', $noRgpd)->assertStatus(422);

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_submission_rejects_non_pdf(): void
    {
        $payload = $this->validPayload([
            'file' => UploadedFile::fake()->create('virus.php', 10, 'text/x-php'),
        ]);

        $this->postJson('/api/submissions', $payload)->assertStatus(422);
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_submissions_index_requires_auth(): void
    {
        $this->getJson('/api/admin/submissions')->assertStatus(401);
    }

    public function test_submissions_index_forbidden_for_non_admin(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 0]));

        $this->getJson('/api/admin/submissions')->assertStatus(403);
    }

    public function test_admin_sees_submissions_and_stats(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 1]));
        Submission::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/submissions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.total', 3)
            ->assertJsonCount(3, 'data.submissions');
    }

    public function test_admin_can_update_status(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 1]));
        $submission = Submission::factory()->create(['status' => 'pending']);

        $response = $this->putJson("/api/admin/submissions/{$submission->id}/status", [
            'status' => 'accepted',
            'admin_notes' => 'Intéressant',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('submissions', ['id' => $submission->id, 'status' => 'accepted']);
    }

    public function test_invalid_status_is_rejected(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 1]));
        $submission = Submission::factory()->create();

        $this->putJson("/api/admin/submissions/{$submission->id}/status", ['status' => 'nimporte'])
            ->assertStatus(422);
    }

    public function test_statistics_endpoint_works(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 1]));
        Submission::factory()->count(2)->create(['status' => 'pending']);

        $this->getJson('/api/admin/submissions/statistics')
            ->assertStatus(200)
            ->assertJsonPath('data.statistics.total', 2);
    }

    public function test_admin_can_send_email_to_submitter(): void
    {
        Mail::fake();
        Passport::actingAs(User::factory()->create(['role' => 1]));
        $submission = Submission::factory()->create();

        $response = $this->postJson("/api/admin/submissions/{$submission->id}/send-email", [
            'subject' => 'Votre soumission',
            'message' => 'Bonjour, votre manuscrit a bien été reçu.',
        ]);

        $response->assertStatus(200);
        Mail::assertSent(\App\Mail\SubmissionReply::class);
    }

    public function test_send_email_requires_subject_and_message(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 1]));
        $submission = Submission::factory()->create();

        $this->postJson("/api/admin/submissions/{$submission->id}/send-email", [])
            ->assertStatus(422);
    }

    public function test_send_email_404_for_unknown_submission(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 1]));

        $this->postJson('/api/admin/submissions/999999/send-email', [
            'subject' => 'Sujet',
            'message' => 'Message',
        ])->assertStatus(404);
    }

    public function test_admin_can_delete_submission(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 1]));
        $submission = Submission::factory()->create();

        $this->deleteJson("/api/admin/submissions/{$submission->id}")->assertStatus(200);
        $this->assertDatabaseMissing('submissions', ['id' => $submission->id]);
    }

    public function test_per_page_is_clamped(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 1]));
        Submission::factory()->count(60)->create();

        $response = $this->getJson('/api/admin/submissions?per_page=999999');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(50, count($response->json('data.submissions')));
    }
}
