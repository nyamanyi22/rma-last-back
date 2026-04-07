<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPasswordExpiry
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/logout') || $request->is('api/me') || $request->is('api/profile') || $request->is('api/profile/password') || $request->is('api/session/refresh')) {
            return $next($request);
        }

        $passwordExpiryDays = Setting::passwordExpiryDays();

        if ($passwordExpiryDays === 0) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $passwordChangedAt = $user->password_changed_at ?? $user->created_at;

        if ($passwordChangedAt && $passwordChangedAt->copy()->addDays($passwordExpiryDays)->isPast()) {
            return response()->json([
                'message' => 'Your password has expired. Please change your password to continue.',
                'password_expired' => true,
                'password_expiry_days' => $passwordExpiryDays,
            ], 403);
        }

        return $next($request);
    }
}
