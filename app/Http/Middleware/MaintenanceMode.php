<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Setting::boolean('maintenance_mode', false)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'The system is currently in maintenance mode. Please try again later.',
            'maintenance_mode' => true,
        ], 503);
    }
}
