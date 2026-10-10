<?php

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubmissionReply extends Mailable
{
    use Queueable, SerializesModels;

    public Submission $submission;
    public string $customSubject;
    public string $customMessage;

    public function __construct(Submission $submission, string $subject, string $message)
    {
        $this->submission = $submission;
        $this->customSubject = $subject;
        $this->customMessage = $message;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.submission_reply',
            with: [
                'submission' => $this->submission,
                'customMessage' => $this->customMessage,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
