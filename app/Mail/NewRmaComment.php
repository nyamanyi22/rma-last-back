<?php

namespace App\Mail;

use App\Models\RMARequest;
use App\Models\RMAComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewRmaComment extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $rma;
    public $comment;

    /**
     * Create a new message instance.
     */
    public function __construct(RMARequest $rma, RMAComment $comment)
    {
        $this->rma = $rma;
        $this->comment = $comment;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Comment on RMA - ' . $this->rma->rma_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.rma.new_comment',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
