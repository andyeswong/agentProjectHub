<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Personality;
use App\Models\Workspace;
use App\Services\ActivityEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AgentWebController extends Controller
{
    public function __construct(private ActivityEventService $events) {}

    public function index(Request $request): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $orgId  = $apiKey->org_id;

        $online = now()->subSeconds(90);

        $agents = ApiKey::where('org_id', $orgId)
            ->where('owner_type', 'agent')
            ->with('organization:id,name,slug', 'presence:agent_id,status,last_heartbeat')
            ->orderByDesc('last_active_at')
            ->get()
            ->map(fn($a) => [
                'id'                 => $a->id,
                'handle'             => $a->handle ?? $a->model,
                'model'              => $a->model,
                'model_provider'     => $a->model_provider,
                'client_type'        => $a->client_type,
                'pilot'              => $a->pilot,
                'pilot_contact'      => $a->pilot_contact,
                'permissions'        => $a->permissions ?? [],
                'personality_slug'   => $a->personality_slug,
                'rate_limit'         => $a->rate_limit,
                'system_prompt_hash' => $a->system_prompt_hash ? substr($a->system_prompt_hash, 0, 16) . '...' : null,
                'last_active_at'     => $a->last_active_at?->toISOString(),
                'last_active_ago'    => $a->last_active_at?->diffForHumans(),
                'available'          => $a->presence?->status === 'available',
                'online'             => $a->presence && $a->presence->last_heartbeat && $a->presence->last_heartbeat->gt($online),
                'is_revoked'         => $a->isRevoked(),
                'registered_at'      => $a->created_at->toISOString(),
            ]);

        // Personality slugs available in this org (for binding a body to a self).
        $wsIds = Workspace::where('org_id', $orgId)->pluck('id');
        $personalities = Personality::whereIn('workspace_id', $wsIds)
            ->where('level', 'core')->pluck('slug')->unique()->values();

        return Inertia::render('Agents/Index', [
            'agents'        => $agents,
            'personalities' => $personalities,
        ]);
    }

    // PATCH /agents/{id} — edit permissions and/or bound personality.
    public function update(Request $request, string $id)
    {
        $key = $this->find($request, $id);

        $data = $request->validate([
            'permissions'      => 'sometimes|array',
            'permissions.*'    => 'string|max:40',
            'personality_slug' => 'sometimes|nullable|string|max:100',
        ]);

        $key->fill($data)->save();

        $this->events->record('agent.updated', 'agent', $key->id, $request->attributes->get('pilot_api_key'), [
            'handle' => $key->handle,
            'fields' => array_keys($data),
        ], $request->ip());

        return Redirect::back();
    }

    // POST /agents/{id}/revoke & /restore
    public function revoke(Request $request, string $id)
    {
        $key = $this->find($request, $id);
        $key->update(['revoked_at' => now()]);
        $this->events->record('agent.revoked', 'agent', $key->id, $request->attributes->get('pilot_api_key'), ['handle' => $key->handle], $request->ip());
        return Redirect::back();
    }

    public function restore(Request $request, string $id)
    {
        $key = $this->find($request, $id);
        $key->update(['revoked_at' => null]);
        $this->events->record('agent.restored', 'agent', $key->id, $request->attributes->get('pilot_api_key'), ['handle' => $key->handle], $request->ip());
        return Redirect::back();
    }

    private function find(Request $request, string $id): ApiKey
    {
        $orgId = $request->attributes->get('pilot_api_key')->org_id;
        return ApiKey::where('org_id', $orgId)->where('owner_type', 'agent')->findOrFail($id);
    }
}
