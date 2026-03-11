<?php

namespace App\Services;

use App\Models\RMARequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\RmaStatusChanged;
use App\Mail\RmaSubmitted;

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
        // Notify admins
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();

        foreach ($admins as $admin) {
            // Mail::to($admin->email)->send(new RmaSubmitted($rma));
            // For now, log it
            \Log::info("Notification: New RMA #{$rma->rma_number} submitted");
        }
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
        // Notify customer
        // Mail::to($rma->customer->email)->send(new RmaStatusChanged($rma, $oldStatus));

        \Log::info("Notification: RMA #{$rma->rma_number} status changed from {$oldStatus} to {$rma->status}");
    }
}