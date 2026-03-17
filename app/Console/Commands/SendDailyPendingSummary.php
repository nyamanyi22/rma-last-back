<?php

namespace App\Console\Commands;

use App\Models\RMARequest;
use App\Models\User;
use App\Mail\RmaDailyPendingSummary;
use App\Enums\RMAStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailyPendingSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rma:send-daily-pending-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a summary of RMAs that have been pending for more than 2 days to all admins.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for pending RMAs older than 2 days...');

        $thresholdDate = now()->subDays(2);

        $pendingRmas = RMARequest::with(['customer'])
            ->whereIn('status', [RMAStatus::PENDING, RMAStatus::UNDER_REVIEW])
            ->where('created_at', '<=', $thresholdDate)
            ->get();

        if ($pendingRmas->isEmpty()) {
            $this->info('No pending RMAs found matching criteria.');
            return;
        }

        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();

        if ($admins->isEmpty()) {
            $this->error('No administrators found to notify.');
            return;
        }

        foreach ($admins as $admin) {
            Mail::to($admin->email)->queue(new RmaDailyPendingSummary($pendingRmas));
        }

        $this->info('Daily summary email queued for ' . $admins->count() . ' admin(s).');
        $this->info('Total RMAs in summary: ' . $pendingRmas->count());
    }
}
