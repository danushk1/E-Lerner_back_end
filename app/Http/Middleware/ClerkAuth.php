<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ClerkAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized - Missing Token'], 401);
        }

        $token = substr($authHeader, 7); // Extract token
        \Log::info("Token received", ['token' => $token]);

        try {
            // Fetch Clerk's JWKS
            $jwks = Http::get('https://intimate-piglet-39.clerk.accounts.dev/.well-known/jwks.json')->json();

            $decoded = JWT::decode($token, JWK::parseKeySet($jwks));

            // Optionally store decoded user data in the request
            $request->attributes->set('clerk_user', (array)$decoded);

        
        } catch (\Exception $e) {
            \Log::error('Error fetching JWKS: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
        }
        
        return $next($request);
    }
}
