<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Personality;
use App\Models\Workspace;
use App\Services\ActivityEventService;
use App\Services\PersonalityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Identity layer API. A personality is the SELF a stateless body wears; one
 * self (slug) is a cascade of layers (core -> runtime -> channel). resolve()
 * assembles the effective identity for the CALLING body (its client_type) and
 * the channel it names — so the runtime carries no local soul files.
 */
class PersonalityController extends Controller
{
    public function __construct(
        private PersonalityService $personalities,
        private ActivityEventService $events,
    ) {}

    // POST /api/v1/personalities/resolve
    // Body: {slug?, channel?, client_type?}. The body it resolves FOR defaults
    // to the calling api_key (its client_type + bound personality_slug); pass
    // overrides to preview another body's view.
    public function resolve(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $slug = $request->input('slug') ?: $apiKey->personality_slug;
        if (! $slug) {
            return response()->json([
                'error' => 'no_personality',
                'hint'  => 'Pass slug, or bind this api_key to a personality (api_keys.personality_slug).',
            ], 422);
        }

        $clientType = $request->input('client_type', $apiKey->client_type);
        $channel    = $request->input('channel'); // optional

        $ws = $this->workspaceForSlug($apiKey, $slug, $request->input('workspace_id'));
        if (! $ws) {
            return response()->json(['error' => 'no_workspace'], 422);
        }

        $identity = $this->personalities->resolve($slug, $ws->id, $clientType, $channel);
        if (! $identity) {
            return response()->json([
                'error' => 'not_found',
                'hint'  => "No active personality '{$slug}' (with a core layer) in this org.",
            ], 404);
        }

        return response()->json([
            'personality'  => $identity,
            'worn_by'      => ['handle' => $apiKey->handle, 'client_type' => $apiKey->client_type],
            '_meta'        => [
                'resolved_for' => $identity['resolved_for'],
                'layers'       => count($identity['layers']),
                'missing'      => $identity['missing'],
                'hint'         => 'Inject `soul` + `rules` + `register` as system context. `refs` is a LAZY pointer index — fetch each only when its `when` applies (load:eager = pull now, load:lazy = keep the pointer, fetch on demand). Auto-load `scopes` via memory_consolidate. The body holds no identity state; heavy context stays cold until triggered.',
            ],
            'next_steps'   => [
                ['action' => 'Auto-load a scope', 'method' => 'POST', 'endpoint' => '/api/v1/memory/consolidate', 'body' => ['q' => '<scope topic>']],
            ],
        ]);
    }

    // GET /api/v1/personalities/{slug}
    // Raw cascade tree (all layers) for inspection/editing.
    public function show(Request $request, string $slug): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');
        $ws = $this->workspaceForSlug($apiKey, $slug, $request->query('workspace_id'));
        if (! $ws) {
            return response()->json(['error' => 'no_workspace'], 422);
        }

        $layers = Personality::where('workspace_id', $ws->id)
            ->where('slug', $slug)
            ->orderByRaw("CASE level WHEN 'core' THEN 0 WHEN 'runtime' THEN 1 ELSE 2 END")
            ->orderBy('match_client_type')
            ->orderBy('match_channel')
            ->get();

        if ($layers->isEmpty()) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json([
            'slug'   => $slug,
            'name'   => $layers->firstWhere('level', 'core')?->name,
            'layers' => $layers,
        ]);
    }

    // POST /api/v1/personalities
    // Upsert one layer of a cascade (idempotent on slug+level+client_type+channel).
    public function upsert(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $data = $request->validate([
            'slug'              => 'required|string|max:100',
            'name'              => 'nullable|string|max:120',
            'level'             => 'required|in:core,runtime,channel',
            'match_client_type' => 'nullable|string|max:60',
            'match_channel'     => 'nullable|string|max:60',
            'soul'              => 'nullable|string',
            'register'          => 'nullable|string|max:200',
            'model_pref'        => 'nullable|string|max:120',
            'scopes'            => 'nullable|array',
            'tools'             => 'nullable|array',
            'rules'             => 'nullable|array',
            'refs'              => 'nullable|array',
            'refs.*.kind'       => 'required_with:refs|in:memory,skill,tool,scope',
            'refs.*.ref'        => 'required_with:refs|string',
            'refs.*.when'       => 'nullable|string',
            'refs.*.load'       => 'nullable|in:eager,lazy',
            'refs.*.note'       => 'nullable|string',
            'meta'              => 'nullable|array',
            'status'            => 'nullable|in:draft,active',
            'workspace_id'      => 'nullable|uuid',
        ]);

        // Layer-shape guards: deeper layers must name what they match.
        if ($data['level'] !== 'core' && empty($data['match_client_type'])) {
            return response()->json(['error' => 'match_client_type required for runtime/channel layers'], 422);
        }
        if ($data['level'] === 'channel' && empty($data['match_channel'])) {
            return response()->json(['error' => 'match_channel required for channel layers'], 422);
        }

        $ws = $this->resolveTargetWorkspace($apiKey, $data['workspace_id'] ?? null);
        if (! $ws) {
            return response()->json(['error' => 'no_workspace'], 422);
        }

        // Resolve parent: runtime/channel layers hang off the core of the same slug.
        $parentId = null;
        if ($data['level'] !== 'core') {
            $parentId = Personality::where('workspace_id', $ws->id)
                ->where('slug', $data['slug'])
                ->where('level', 'core')
                ->value('id');
        }

        $match = [
            'workspace_id'      => $ws->id,
            'slug'              => $data['slug'],
            'level'             => $data['level'],
            'match_client_type' => $data['match_client_type'] ?? null,
            'match_channel'     => $data['match_channel'] ?? null,
        ];

        $existing = Personality::where($match)->first();

        $attrs = array_merge($data, [
            'parent_id'       => $parentId,
            'last_updated_by' => $apiKey->id,
            'version'         => $existing ? $existing->version + 1 : 1,
        ]);
        unset($attrs['workspace_id']); // already in $match

        if ($existing) {
            $existing->update($attrs);
            $layer = $existing->fresh();
            $event = 'personality.updated';
        } else {
            $layer = Personality::create(array_merge($match, $attrs, ['created_by' => $apiKey->id]));
            $event = 'personality.created';
        }

        $this->events->record($event, 'personality', $layer->id, $apiKey, [
            'slug'  => $layer->slug,
            'level' => $layer->level,
            'match' => trim(($layer->match_client_type ?? '') . '/' . ($layer->match_channel ?? ''), '/'),
        ]);

        return response()->json(['status' => $existing ? 'updated' : 'created', 'layer' => $layer], $existing ? 200 : 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Find the workspace holding this slug's core layer (or the org default). */
    private function workspaceForSlug($apiKey, string $slug, ?string $workspaceId): ?Workspace
    {
        if ($workspaceId) {
            return Workspace::where('org_id', $apiKey->org_id)->where('id', $workspaceId)->first();
        }

        $orgWsIds = Workspace::where('org_id', $apiKey->org_id)->pluck('id');
        $hit = Personality::whereIn('workspace_id', $orgWsIds)
            ->where('slug', $slug)
            ->where('level', 'core')
            ->first();

        return $hit ? Workspace::find($hit->workspace_id) : $this->resolveTargetWorkspace($apiKey, null);
    }

    private function resolveTargetWorkspace($apiKey, ?string $workspaceId): ?Workspace
    {
        $query = Workspace::where('org_id', $apiKey->org_id);
        if ($workspaceId) {
            return $query->where('id', $workspaceId)->first();
        }
        return $query->orderBy('name')->first();
    }
}
