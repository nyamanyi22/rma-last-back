<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'system_name' => 'RMA System',
            'support_email' => 'support@example.com',
            'timezone' => 'UTC+3',
            'session_duration' => '8',
            'max_file_size' => '5',
            'language' => 'en',
            'disable_all_notifications' => '0',
            'maintenance_mode' => '0',
            'allow_registrations' => '1',
            'two_factor_required' => '0',
            'min_password_length' => '8',
            'password_expiry_days' => '90',
            'auto_backup' => '1',
            'rma_email_alerts_to_staff' => '1',
            'session_timeout' => '1',
            'session_timeout_alerts' => '1',
            'debug_mode' => '0',
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
