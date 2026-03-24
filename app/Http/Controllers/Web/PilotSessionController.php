<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PilotToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PilotSessionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['pilot_token' => 'required|string']);

        $hashed = hash('sha256', $request->pilot_token);

        $pilotToken = PilotToken::where('token', $hashed)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('apiKey.organization')
            ->first();

        if (!$pilotToken) {
            return back()->with('error', 'Invalid or expired pilot token. Ask your agent to generate a new one.');
        }

        // Mark the login token as used
        $pilotToken->update(['used_at' => now()]);

        $apiKey = $pilotToken->apiKey;
        $org    = $apiKey->organization;

        // Create a session token (8h expiry)
        $rawSession = 'sess_' . Str::random(60);
        PilotToken::create([
            'api_key_id' => $apiKey->id,
            'token'      => hash('sha256', $rawSession),
            'pilot_name' => $pilotToken->pilot_name,
            'expires_at' => now()->addHours(8),
        ]);

        session([
            'pilot_session_token' => $rawSession,
            'pilot_name'          => $pilotToken->pilot_name,
            'agent_id'            => $apiKey->id,
            'agent_model'         => $apiKey->model,
            'agent_client_type'   => $apiKey->client_type,
            'agent_permissions'   => $apiKey->permissions,
            'org_id'              => $org->id,
            'org_name'            => $org->name,
            'org_slug'            => $org->slug,
        ]);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Expire the session token in DB
        $rawToken = session('pilot_session_token');
        if ($rawToken) {
            PilotToken::where('token', hash('sha256', $rawToken))
                ->update(['expires_at' => now()]);
        }

        $request->session()->flush();

        return redirect()->route('login');
    }
}
