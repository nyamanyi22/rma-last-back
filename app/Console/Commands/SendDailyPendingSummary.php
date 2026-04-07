<?php

namespace App\Console\Commands;

use App\Models\RMARequest;
use App\Models\User;
use App\Mail\RmaDailyPendingSummary;
use App\Enums\RMAStatus;
use App\Services\EmailService;
use Illuminate\Console\Command;

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

    public function __construct(private readonly EmailService $mailService)
    {
        parent::__construct();
    }

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
            $this->mailService->queue(
                $admin->email,
                new RmaDailyPendingSummary($pendingRmas),
                EmailService::STAFF_DAILY_PENDING_SUMMARY,
                'staff_daily_pending_summary',
                [
                    'staff_email' => $admin->email,
                    'pending_count' => $pendingRmas->count(),
                ],
                true
            );
        }

        $this->info('Daily summary email queued for ' . $admins->count() . ' admin(s).');
        $this->info('Total RMAs in summary: ' . $pendingRmas->count());
    }
}
