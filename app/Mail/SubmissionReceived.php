<?php

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubmissionReceived extends Mailable
{
    use Queueable, SerializesModels;

    public Submission $submission;

    public function __construct(Submission $submission)
    {
        $this->submission = $submission;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nous avons bien reçu votre soumission — Les Éditions du Djembé',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.submission_received',
            with: [
                'submission' => $this->submission,
                'typeName' => $this->typeName(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function typeName(): string
    {
        return match ($this->submission->type) {
            'book' => 'Livre',
            'memoir' => 'Mémoire',
            'article' => 'Article',
            default => $this->submission->type,
        };
    }
}
