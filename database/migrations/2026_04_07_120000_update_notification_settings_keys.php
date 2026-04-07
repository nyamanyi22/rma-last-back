<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $legacyEmailNotifications = DB::table('settings')->where('key', 'email_notifications')->value('value');
        $legacyRmaAlerts = DB::table('settings')->where('key', 'rma_email_alerts')->value('value');

        DB::table('settings')->updateOrInsert(
            ['key' => 'disable_all_notifications'],
            [
                'value' => $legacyEmailNotifications === '0' ? '1' : '0',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'rma_email_alerts_to_staff'],
            [
                'value' => $legacyRmaAlerts ?? '1',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('settings')->whereIn('key', [
            'email_notifications',
            'rma_email_alerts',
            'audit_logging',
            'rate_limiting',
        ])->delete();
    }

    public function down(): void
    {
        $now = now();

        $disableAll = DB::table('settings')->where('key', 'disable_all_notifications')->value('value');
        $staffAlerts = DB::table('settings')->where('key', 'rma_email_alerts_to_staff')->value('value');

        DB::table('settings')->updateOrInsert(
            ['key' => 'email_notifications'],
            [
                'value' => $disableAll === '1' ? '0' : '1',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'rma_email_alerts'],
            [
                'value' => $staffAlerts ?? '1',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }
};
