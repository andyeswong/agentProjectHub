<?php

namespace App\Http\Middleware;

use App\Models\PilotToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = session('pilot_session_token');

        if ($rawToken) {
            $exists = PilotToken::where('token', hash('sha256', $rawToken))
                ->where('expires_at', '>', now())
                ->exists();

            if ($exists) {
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
