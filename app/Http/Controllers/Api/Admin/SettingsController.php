<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private array $defaultSettings = [
        'system_name' => 'RMA System',
        'support_email' => '',
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

    /**
     * Get all settings as key-value pairs
     */
    public function index()
    {
        $settings = $this->resolvedSettings();
        
        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update multiple settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'system_name' => 'nullable|string|max:255',
            'support_email' => 'nullable|email|max:255',
            'timezone' => 'nullable|string|max:50',
            'session_duration' => 'nullable|string|max:10',
            'max_file_size' => 'nullable|string|max:10',
            'language' => 'nullable|string|max:10',
            'disable_all_notifications' => 'nullable',
            'maintenance_mode' => 'nullable',
            'allow_registrations' => 'nullable',
            'two_factor_required' => 'nullable',
            'min_password_length' => 'nullable|integer|min:4|max:20',
            'password_expiry_days' => 'nullable|integer|min:0|max:365',
            'auto_backup' => 'nullable',
            'rma_email_alerts_to_staff' => 'nullable',
            'session_timeout' => 'nullable',
            'session_timeout_alerts' => 'nullable',
            'debug_mode' => 'nullable',
        ]);

        if (array_key_exists('session_timeout', $validated) && !array_key_exists('session_timeout_alerts', $validated)) {
            $validated['session_timeout_alerts'] = $validated['session_timeout'];
        }

        if (array_key_exists('session_timeout_alerts', $validated) && !array_key_exists('session_timeout', $validated)) {
            $validated['session_timeout'] = $validated['session_timeout_alerts'];
        }
        
        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
            );
        }

        Setting::forgetMany(array_keys($validated));

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
            'data' => $this->resolvedSettings()
        ]);
    }

    /**
     * Public portal settings for frontend branding.
     */
    public function portalSettings()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'portal_name' => Setting::portalName(),
                'support_email' => Setting::supportEmail(),
                'disable_all_notifications' => Setting::disableAllNotifications(),
                'rma_email_alerts_to_staff' => Setting::rmaEmailAlertsToStaffEnabled(),
                'session_timeout_alerts' => Setting::sessionTimeoutAlertsEnabled(),
                'session_duration_hours' => Setting::sessionDurationHours(),
            ],
        ]);
    }

    private function resolvedSettings()
    {
        $settings = collect($this->defaultSettings)
            ->merge(Setting::all()->pluck('value', 'key'));

        $sessionTimeout = $settings->get('session_timeout_alerts', $settings->get('session_timeout', '1'));

        return $settings
            ->put('session_timeout', $sessionTimeout)
            ->put('session_timeout_alerts', $sessionTimeout);
    }

    /**
     * Get dynamic system information
     */
    public function getSystemInfo()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'php_version' => phpversion(),
                'laravel_version' => app()->version(),
                'database' => config('database.default'),
                'node_version' => '20.x' // Static as requested
            ]
        ]);
    }

    public function getReturnPolicy()
    {
        $policy = Setting::where('key', 'return_policy')->first();
        
        $defaultPolicy = "1. Returns are accepted within 30 days of purchase.\n2. Items must be in original condition with tags attached.\n3. Return shipping costs are the responsibility of the customer unless the item is defective.\n4. Refunds will be issued to the original payment method.";

        return response()->json([
            'success' => true,
            'data' => $policy ? $policy->value : $defaultPolicy
        ]);
    }

    public function updateReturnPolicy(Request $request)
    {
        $request->validate([
            'return_policy' => 'required|string'
        ]);

        Setting::updateOrCreate(
            ['key' => 'return_policy'],
            ['value' => $request->return_policy]
        );

        Setting::forgetKey('return_policy');

        return response()->json([
            'success' => true,
            'message' => 'Return policy updated successfully.'
        ]);
    }
}
