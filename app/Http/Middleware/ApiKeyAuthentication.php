<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuthentication
{
    public function __construct(private ApiKeyService $apiKeyService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            return response()->json(['error' => 'Missing API key'], 401);
        }

        $apiKey = $this->apiKeyService->resolve($bearer);

        if (!$apiKey) {
            return response()->json(['error' => 'Invalid or revoked API key'], 401);
        }

        // Touch last_active_at (throttled: only update if > 1 min ago)
        if (!$apiKey->last_active_at || $apiKey->last_active_at->diffInMinutes(now()) >= 1) {
            $apiKey->update(['last_active_at' => now()]);
        }

        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
