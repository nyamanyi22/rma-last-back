<?php

namespace App\Services;

use App\Models\RMARequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminNewRmaNotification;
use App\Mail\AdminHighPriorityRmaNotification;
use App\Mail\AdminRmaStatusChangedNotification;
use App\Enums\RMAPriority;

use App\Notifications\AdminRMANotification;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function __construct(private readonly EmailService $mailService)
    {
    }

    /**
     * Send notification for new RMA submission
     */
    public function newRmaSubmitted(RMARequest $rma): void
    {
        // Get all admins and CSRs
        $staff = User::whereIn('role', ['csr', 'admin', 'super_admin'])->get();

        if ($staff->isEmpty()) {
            \Log::warning("No staff found to notify for RMA #{$rma->rma_number}");
            return;
        }

        // Send database notification to all staff
        Notification::send($staff, new AdminRMANotification([
            'rma_id' => $rma->id,
            'rma_number' => $rma->rma_number,
            'title' => 'New RMA Submitted',
            'message' => "A new RMA (#{$rma->rma_number}) has been submitted by {$rma->customer->full_name}.",
            'type' => 'new_rma',
            'action_url' => "/admin/rma",
            'created_by_name' => $rma->customer->full_name,
        ]));

        // Send instant email notification to staff
        foreach ($staff as $staffMember) {
            $this->mailService->queue(
                $staffMember->email,
                new AdminNewRmaNotification($rma),
                EmailService::STAFF_NEW_RMA,
                'staff_new_rma_alert',
                [
                    'rma_id' => $rma->id,
                    'rma_number' => $rma->rma_number,
                    'staff_email' => $staffMember->email,
                ],
                true
            );
        }

        // Special alert for high priority
        $admins = $staff->filter(fn($u) => $u->isAdmin());
        if ($this->isHighPriority($rma)) {
            foreach ($admins as $admin) {
                $this->mailService->queue(
                    $admin->email,
                    new AdminHighPriorityRmaNotification($rma),
                    EmailService::STAFF_HIGH_PRIORITY_RMA,
                    'staff_high_priority_rma_alert',
                    [
                        'rma_id' => $rma->id,
                        'rma_number' => $rma->rma_number,
                        'staff_email' => $admin->email,
                    ],
                    true
                );
            }
        }

        \Log::info("Admin notifications processed for RMA #{$rma->rma_number}");
    }

    /**
     * Send notification for status change
     */
    public function statusChanged(RMARequest $rma, string $oldStatus, User $changer): void
    {
        $newStatusLabel = $rma->status instanceof \App\Enums\RMAStatus ? $rma->status->label() : ucfirst($rma->status);
        
        // Notify all staff about the status change (except the person who changed it)
        $staff = User::whereIn('role', ['csr', 'admin', 'super_admin'])
            ->where('id', '!=', $changer->id)
            ->get();

        if ($staff->isNotEmpty()) {
            Notification::send($staff, new AdminRMANotification([
                'rma_id' => $rma->id,
                'rma_number' => $rma->rma_number,
                'title' => 'RMA Status Updated',
                'message' => "RMA #{$rma->rma_number} status changed to '{$newStatusLabel}' by {$changer->full_name}.",
                'type' => 'status_update',
                'action_url' => "/admin/rma",
                'created_by_name' => $changer->full_name,
            ]));
        }

        foreach ($staff as $staffMember) {
            $this->mailService->send(
                $staffMember->email,
                new AdminRmaStatusChangedNotification($rma, $oldStatus, $newStatusLabel, $changer->full_name),
                EmailService::STAFF_RMA_STATUS_CHANGE,
                'staff_rma_status_alert',
                [
                    'rma_id' => $rma->id,
                    'rma_number' => $rma->rma_number,
                    'staff_email' => $staffMember->email,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatusLabel,
                ],
                true
            );
        }

        \Log::info("Status change notification processed for RMA #{$rma->rma_number}");
    }

    /**
     * Send notification for new internal note
     */
    public function internalNoteAdded(RMARequest $rma, $comment, User $author): void
    {
        // Notify all other staff
        $staff = User::whereIn('role', ['csr', 'admin', 'super_admin'])
            ->where('id', '!=', $author->id)
            ->get();

        if ($staff->isNotEmpty()) {
            Notification::send($staff, new AdminRMANotification([
                'rma_id' => $rma->id,
                'rma_number' => $rma->rma_number,
                'title' => 'New Internal Note',
                'message' => "{$author->full_name} added an internal note to RMA #{$rma->rma_number}.",
                'type' => 'internal_note',
                'action_url' => "/admin/rma",
                'created_by_name' => $author->full_name,
            ]));
        }
    }

    /**
     * Check if RMA is high priority
     */
    private function isHighPriority(RMARequest $rma): bool
    {
        $priority = $rma->priority->value ?? $rma->priority;
        return in_array($priority, ['high', 'urgent', RMAPriority::HIGH->value], true);
    }
}
