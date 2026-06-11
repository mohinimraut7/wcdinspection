<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next, $role = null)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided.',
            ], 401);
        }

        try {
            $decoded = JWT::decode($token, new Key(config('app.jwt_secret'), 'HS256'));
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        // Role check — same as Node.js isDistrictAdmin, isSuperAdmin, etc.
        if ($role && isset($decoded->role) && $decoded->role !== $role) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Insufficient role.',
            ], 403);
        }

        // Attach user to request (same as req.user in Node.js)
        $request->merge(['auth_user' => $decoded]);

        return $next($request);
    }
}
