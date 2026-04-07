<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return Cache::rememberForever("setting:{$key}", function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    public static function portalName(): string
    {
        return static::getValue('system_name', config('app.name', 'RMA System')) ?: 'RMA System';
    }

    public static function supportEmail(): ?string
    {
        return static::getValue('support_email');
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        $value = static::getValue($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? in_array($value, ['1', 1, true], true);
    }

    public static function integer(string $key, int $default = 0): int
    {
        $value = static::getValue($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function disableAllNotifications(): bool
    {
        return static::boolean('disable_all_notifications', false);
    }

    public static function rmaEmailAlertsToStaffEnabled(): bool
    {
        return static::boolean('rma_email_alerts_to_staff', true);
    }

    public static function sessionTimeoutAlertsEnabled(): bool
    {
        $explicit = static::getValue('session_timeout_alerts');

        if ($explicit !== null) {
            return static::boolean('session_timeout_alerts', true);
        }

        return static::boolean('session_timeout', true);
    }

    public static function sessionDurationHours(): int
    {
        return max(1, static::integer('session_duration', 8));
    }

    public static function minPasswordLength(): int
    {
        return min(20, max(4, static::integer('min_password_length', 8)));
    }

    public static function passwordExpiryDays(): int
    {
        return min(365, max(0, static::integer('password_expiry_days', 90)));
    }

    public static function forgetKey(string $key): void
    {
        Cache::forget("setting:{$key}");
    }

    public static function forgetMany(array $keys): void
    {
        foreach ($keys as $key) {
            static::forgetKey($key);
        }
    }
}
