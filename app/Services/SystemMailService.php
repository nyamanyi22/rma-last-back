<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SystemMailService
{
    public function canSendAnyEmail(): bool
    {
        return Setting::emailNotificationsEnabled();
    }

    public function canSendRmaStaffAlerts(): bool
    {
        return $this->canSendAnyEmail() && Setting::rmaEmailAlertsEnabled();
    }

    public function send(mixed $recipient, Mailable $mailable, string $context, array $meta = [], bool $requiresRmaStaffAlerts = false): bool
    {
        if (!$this->canSendAnyEmail()) {
            Log::info('Skipped mail because email notifications are disabled.', [
                'context' => $context,
                ...$meta,
            ]);

            return false;
        }

        if ($requiresRmaStaffAlerts && !$this->canSendRmaStaffAlerts()) {
            Log::info('Skipped mail because RMA staff email alerts are disabled.', [
                'context' => $context,
                ...$meta,
            ]);

            return false;
        }

        try {
            Mail::to($recipient)->send($mailable);

            Log::info('Mail sent successfully.', [
                'context' => $context,
                'mailer' => config('mail.default'),
                ...$meta,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('Mail send failed.', [
                'context' => $context,
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
                ...$meta,
            ]);

            return false;
        }
    }

    public function queue(mixed $recipient, Mailable $mailable, string $context, array $meta = [], bool $requiresRmaStaffAlerts = false): bool
    {
        if (!$this->canSendAnyEmail()) {
            Log::info('Skipped queued mail because email notifications are disabled.', [
                'context' => $context,
                ...$meta,
            ]);

            return false;
        }

        if ($requiresRmaStaffAlerts && !$this->canSendRmaStaffAlerts()) {
            Log::info('Skipped queued mail because RMA staff email alerts are disabled.', [
                'context' => $context,
                ...$meta,
            ]);

            return false;
        }

        try {
            Mail::to($recipient)->queue($mailable);

            Log::info('Mail queued successfully.', [
                'context' => $context,
                'mailer' => config('mail.default'),
                ...$meta,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('Queued mail failed.', [
                'context' => $context,
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
                ...$meta,
            ]);

            return false;
        }
    }
}
