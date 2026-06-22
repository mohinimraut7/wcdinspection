<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['success' => false, 'message' => 'Token missing ❌'], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key(config('app.jwt_secret'), 'HS256'));
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token ❌'], 401);
        }

        // Role check
        if (!empty($roles) && !in_array($decoded->role, $roles)) {
            return response()->json(['success' => false, 'message' => 'Access denied ❌'], 403);
        }

        // Convert stdClass to array before merging
        $authUser = [
            'id'       => $decoded->id,
            // 'username' => $decoded->username,
                'username' => $decoded->username ?? null,
            'role'     => $decoded->role,
        ];

        $request->merge(['auth_user' => $authUser]);

        return $next($request);
    }
}