<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated. Please login first.',
            ], 401);
        }

        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Forbidden. Super admin access required.',
            ], 403);
        }

        return $next($request);
    }
}
