<?php

namespace App\Http\Middleware;

use App\Models\PilotToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePilotSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = session('pilot_session_token');

        if (!$rawToken) {
            return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
        }

        $pilotToken = PilotToken::where('token', hash('sha256', $rawToken))
            ->where('expires_at', '>', now())
            ->with('apiKey.organization')
            ->first();

        if (!$pilotToken || !$pilotToken->apiKey) {
            session()->forget(['pilot_session_token', 'pilot_name', 'agent_id', 'org_id']);
            return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
        }

        $request->attributes->set('pilot_api_key', $pilotToken->apiKey);

        return $next($request);
    }
}
