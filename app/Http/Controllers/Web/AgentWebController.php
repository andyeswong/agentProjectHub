<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentWebController extends Controller
{
    public function index(Request $request): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $orgId  = $apiKey->org_id;

        $agents = ApiKey::where('org_id', $orgId)
            ->where('owner_type', 'agent')
            ->with('organization:id,name,slug')
            ->orderByDesc('last_active_at')
            ->get()
            ->map(fn($a) => [
                'id'                 => $a->id,
                'model'              => $a->model,
                'model_provider'     => $a->model_provider,
                'client_type'        => $a->client_type,
                'pilot'              => $a->pilot,
                'pilot_contact'      => $a->pilot_contact,
                'permissions'        => $a->permissions,
                'rate_limit'         => $a->rate_limit,
                'system_prompt_hash' => $a->system_prompt_hash
                    ? substr($a->system_prompt_hash, 0, 16) . '...'
                    : null,
                'last_active_at'     => $a->last_active_at?->toISOString(),
                'last_active_ago'    => $a->last_active_at?->diffForHumans(),
                'is_revoked'         => $a->isRevoked(),
                'registered_at'      => $a->created_at->toISOString(),
            ]);

        return Inertia::render('Agents/Index', ['agents' => $agents]);
    }
}
