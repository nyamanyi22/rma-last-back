<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailService
{
    public const PASSWORD_RESET = 'password_reset';
    public const EMAIL_VERIFICATION = 'email_verification';
    public const TWO_FACTOR = 'two_factor';
    public const CUSTOMER_RMA_STATUS_UPDATE = 'customer_rma_status_update';
    public const CUSTOMER_RMA_SUBMITTED = 'customer_rma_submitted';
    public const CUSTOMER_RMA_COMMENT = 'customer_rma_comment';
    public const STAFF_NEW_RMA = 'staff_new_rma';
    public const STAFF_RMA_STATUS_CHANGE = 'staff_rma_status_change';
    public const STAFF_DAILY_PENDING_SUMMARY = 'staff_daily_pending_summary';
    public const STAFF_HIGH_PRIORITY_RMA = 'staff_high_priority_rma';

    private const CRITICAL_EMAIL_TYPES = [
        self::PASSWORD_RESET,
        self::EMAIL_VERIFICATION,
        self::TWO_FACTOR,
    ];

    private const STAFF_RMA_ALERT_TYPES = [
        self::STAFF_NEW_RMA,
        self::STAFF_RMA_STATUS_CHANGE,
        self::STAFF_HIGH_PRIORITY_RMA,
    ];

    public function shouldSendEmail(string $emailType): bool
    {
        if (in_array($emailType, self::CRITICAL_EMAIL_TYPES, true)) {
            return true;
        }

        if (Setting::disableAllNotifications()) {
            return false;
        }

        if (in_array($emailType, self::STAFF_RMA_ALERT_TYPES, true)) {
            return Setting::rmaEmailAlertsToStaffEnabled();
        }

        return true;
    }

    public function send(mixed $recipient, Mailable $mailable, string $emailType, string $context, array $meta = []): bool
    {
        if (!$this->shouldSendEmail($emailType)) {
            Log::info('Skipped email due to notification settings.', [
                'email_type' => $emailType,
                'context' => $context,
                ...$meta,
            ]);

            return false;
        }

        try {
            Mail::to($recipient)->send($mailable);

            Log::info('Email sent successfully.', [
                'email_type' => $emailType,
                'context' => $context,
                'mailer' => config('mail.default'),
                ...$meta,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('Email send failed.', [
                'email_type' => $emailType,
                'context' => $context,
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
                ...$meta,
            ]);

            return false;
        }
    }

    public function queue(mixed $recipient, Mailable $mailable, string $emailType, string $context, array $meta = []): bool
    {
        if (!$this->shouldSendEmail($emailType)) {
            Log::info('Skipped queued email due to notification settings.', [
                'email_type' => $emailType,
                'context' => $context,
                ...$meta,
            ]);

            return false;
        }

        try {
            Mail::to($recipient)->queue($mailable);

            Log::info('Email queued successfully.', [
                'email_type' => $emailType,
                'context' => $context,
                'mailer' => config('mail.default'),
                ...$meta,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('Queued email failed.', [
                'email_type' => $emailType,
                'context' => $context,
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
                ...$meta,
            ]);

            return false;
        }
    }
}
