<?php

namespace App\Services;

use App\Models\RMARequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminNewRmaNotification;
use App\Mail\AdminHighPriorityRmaNotification;
use App\Enums\RMAPriority;

class NotificationService
{
    /**
     * Send notification for new RMA submission
     *
     * @param RMARequest $rma
     * @return void
     */
    public function newRmaSubmitted(RMARequest $rma): void
    {
        // Get all admins and super admins
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();

        if ($admins->isEmpty()) {
            \Log::warning("No admins found to notify for RMA #{$rma->rma_number}");
            return;
        }

        // Send instant notification to all admins
        foreach ($admins as $admin) {
            Mail::to($admin->email)->queue(new AdminNewRmaNotification($rma));
        }

        // Send special alert if priority is high or urgent
        if ($this->isHighPriority($rma)) {
            foreach ($admins as $admin) {
                Mail::to($admin->email)->queue(new AdminHighPriorityRmaNotification($rma));
            }
        }

        \Log::info("Admin notifications queued for newly submitted RMA #{$rma->rma_number}");
    }

    /**
     * Check if RMA is high priority
     *
     * @param RMARequest $rma
     * @return bool
     */
    private function isHighPriority(RMARequest $rma): bool
    {
        $priority = $rma->priority->value ?? $rma->priority;
        return in_array($priority, ['high', 'urgent', RMAPriority::HIGH->value], true);
    }

    /**
     * Send notification for status change
     *
     * @param RMARequest $rma
     * @param string $oldStatus
     * @return void
     */
    public function statusChanged(RMARequest $rma, string $oldStatus): void
    {
        $newStatus = $rma->status->value ?? $rma->status;
        \Log::info("Notification: RMA #{$rma->rma_number} status changed from {$oldStatus} to {$newStatus}");
    }
}