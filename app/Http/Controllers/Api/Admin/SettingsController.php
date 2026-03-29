<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get all settings as key-value pairs
     */
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');
        
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
        $settings = $request->all();
        
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
            'data' => Setting::all()->pluck('value', 'key')
        ]);
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

        return response()->json([
            'success' => true,
            'message' => 'Return policy updated successfully.'
        ]);
    }
}
