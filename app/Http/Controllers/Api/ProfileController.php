<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Get authenticated user's profile
     */
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country,
                'address' => $user->address,
                'city' => $user->city,
                'postal_code' => $user->postal_code,
                'role' => $user->role,
                'password_changed_at' => $user->password_changed_at,
            ]
        ]);
    }

    /**
     * Update authenticated user's profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|size:2',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
        ]);

        foreach ($validated as $field => $value) {
            $user->$field = $value;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country,
                'address' => $user->address,
                'city' => $user->city,
                'postal_code' => $user->postal_code,
                'role' => $user->role,
                'role_label' => $user->role_label,
                'short_role_label' => $user->short_role_label,
            ]
        ]);
    }

    /**
     * Update authenticated user's password
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $minLength = Setting::minPasswordLength();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:' . $minLength . '|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->password_changed_at = now();
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }
}
