<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgentSession;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SessionWebController extends Controller
{
    // GET /sessions — what the org's agents are working on (episodic layer).
    public function index(Request $request): Response
    {
        $apiKey  = $request->attributes->get('pilot_api_key');
        $keyIds  = ApiKey::where('org_id', $apiKey->org_id)->pluck('id');

        $sessions = AgentSession::whereIn('api_key_id', $keyIds)
            ->orderByDesc('last_active_at')
            ->limit(60)
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'title'        => $s->title,
                'summary'      => $s->summary,
                'agent_handle' => $s->agent_handle,
                'open_threads' => $s->open_threads ?? [],
                'status'       => $s->status,
                'last_active'  => $s->last_active_at?->diffForHumans(),
                'started'      => $s->started_at?->diffForHumans(),
            ]);

        return Inertia::render('Sessions/Index', [
            'sessions'   => $sessions,
            'open_count' => $sessions->filter(fn($s) => count($s['open_threads']) > 0)->count(),
        ]);
    }
}
