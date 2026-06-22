<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyExternalJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $secret = config('external_sales.jwt_secret');

        if (! $token || ! $secret) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $payload = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            // bad signature, expired (exp), not-yet-valid (nbf), malformed, etc.
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Require an expiry so a leaked token can't be replayed forever.
        if (! isset($payload->exp)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
