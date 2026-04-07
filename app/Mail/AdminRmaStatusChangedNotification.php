<?php

namespace App\Mail;

use App\Models\RMARequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminRmaStatusChangedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public RMARequest $rma,
        public string $oldStatus,
        public string $newStatus,
        public string $changedBy
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'RMA Status Updated - ' . $this->rma->rma_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin.status_changed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
