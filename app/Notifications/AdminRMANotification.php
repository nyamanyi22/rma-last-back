<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminRMANotification extends Notification
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'rma_id' => $this->data['rma_id'] ?? null,
            'rma_number' => $this->data['rma_number'] ?? null,
            'title' => $this->data['title'] ?? 'RMA Update',
            'message' => $this->data['message'] ?? '',
            'type' => $this->data['type'] ?? 'info', // e.g., 'new_rma', 'status_update', 'internal_note'
            'action_url' => $this->data['action_url'] ?? null,
            'created_by_name' => $this->data['created_by_name'] ?? 'System',
        ];
    }
}
