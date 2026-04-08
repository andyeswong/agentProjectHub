<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentMemory;
use App\Models\Workspace;
use App\Services\ActivityEventService;
use App\Services\EmbeddingService;
use App\Services\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    public function __construct(
        private MemoryService $memory,
        private EmbeddingService $embedder,
        private ActivityEventService $events,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/memory
    // List all non-expired memories across the agent's org.
    // Optional: ?workspace_id=<uuid> to filter to a single workspace.
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $apiKey        = $request->attributes->get('api_key');
        [$workspaceIds, $scopeLabel] = $this->resolveWorkspaceIds($apiKey, $request->query('workspace_id'));

        if (empty($workspaceIds)) {
            return $this->noWorkspaceError();
        }

        $query = AgentMemory::whereIn('workspace_id', $workspaceIds)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        if ($request->filled('type')) {
            $query->whereIn('type', explode(',', $request->type));
        }

        if ($request->filled('tags')) {
            foreach (explode(',', $request->tags) as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

        if ($request->filled('key')) {
            $query->where('memory_key', $request->key);
        }

        if ($request->filled('sensitive')) {
            $query->where('is_sensitive', filter_var($request->sensitive, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn($sq) =>
                $sq->where('label', 'like', "%{$q}%")
                   ->orWhere('content', 'like', "%{$q}%")
                   ->orWhere('memory_key', 'like', "%{$q}%")
            );
        }

        $memories = $query->with(['creator', 'lastEditor'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('limit', 50));

        $total = AgentMemory::whereIn('workspace_id', $workspaceIds)->count();

        return response()->json([
            'data' => $memories->map(fn($m) => $m->toPublicArray())->values(),
            '_meta' => [
                'scope'          => $scopeLabel,
                'workspace_ids'  => $workspaceIds,
                'total_memories' => $total,
                'returned'       => $memories->count(),
                'embed_model'    => $this->embedder->model(),
                'hint'           => 'Pass ?workspace_id=<uuid> to narrow results to a single workspace. Use POST /api/v1/memory/search for semantic search.',
            ],
            'next_steps' => [
                ['action' => 'Semantic search',  'method' => 'POST', 'endpoint' => '/api/v1/memory/search', 'body' => ['q' => 'your query here']],
                ['action' => 'Store new memory', 'method' => 'POST', 'endpoint' => '/api/v1/memory'],
                ['action' => 'Upsert by key',    'method' => 'PUT',  'endpoint' => '/api/v1/memory/key/{key}'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/memory
    // Store a new memory. Pass workspace_id in body to target a specific
    // workspace; defaults to the first workspace in the org.
    // ─────────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $apiKey    = $request->attributes->get('api_key');
        $workspace = $this->resolveTargetWorkspace($apiKey, $request->input('workspace_id'));

        if (!$workspace) {
            return $this->noWorkspaceError();
        }

        $data = $request->validate([
            'workspace_id' => 'nullable|uuid',
            'key'          => 'nullable|string|max:255',
            'type'         => 'nullable|in:credential,domain,ip,fact,config,note,skill,other',
            'label'        => 'required|string|max:255',
            'content'      => 'required|string',
            'value'        => 'nullable|array',
            'tags'         => 'nullable|array',
            'tags.*'       => 'string|max:50',
            'is_sensitive' => 'nullable|boolean',
            'expires_at'   => 'nullable|date',
        ]);

        if (!empty($data['key'])) {
            $exists = AgentMemory::where('workspace_id', $workspace->id)
                ->where('memory_key', $data['key'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => "A memory with key \"{$data['key']}\" already exists in workspace \"{$workspace->name}\".",
                    'code'  => 'memory_key_conflict',
                    'hint'  => 'Use PUT /api/v1/memory/key/{key} to update an existing memory by key, or omit the key to create without one.',
                ], 409);
            }
        }

        $mem   = $this->memory->store($data, $workspace->id, $apiKey);
        $total = AgentMemory::where('workspace_id', $workspace->id)->count();

        return response()->json([
            'status' => 'stored',
            'memory' => $mem->toPublicArray(),
            '_meta'  => [
                'workspace_id'   => $workspace->id,
                'workspace_name' => $workspace->name,
                'total_memories' => $total,
                'embedded'       => $mem->isEmbedded(),
                'embed_model'    => $mem->embedding_model ?? null,
                'hint'           => $mem->isEmbedded()
                    ? 'Memory is embedded and will appear in semantic search results.'
                    : 'Ollama was unreachable — memory stored without embedding. It will not appear in semantic search until re-embedded.',
            ],
            'next_steps' => [
                ['action' => 'Search memories',      'method' => 'POST', 'endpoint' => '/api/v1/memory/search', 'body' => ['q' => $mem->label]],
                ['action' => 'Retrieve this memory', 'method' => 'GET',  'endpoint' => "/api/v1/memory/{$mem->id}"],
                ['action' => 'List all memories',    'method' => 'GET',  'endpoint' => '/api/v1/memory'],
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/memory/{id}
    // Get a single memory (org-scoped, full unmasked value returned)
    // ─────────────────────────────────────────────────────────────────────
    public function show(Request $request, string $id): JsonResponse
    {
        $apiKey        = $request->attributes->get('api_key');
        [$workspaceIds] = $this->resolveWorkspaceIds($apiKey);

        if (empty($workspaceIds)) {
            return $this->noWorkspaceError();
        }

        $mem = AgentMemory::whereIn('workspace_id', $workspaceIds)
            ->with(['creator', 'lastEditor'])
            ->findOrFail($id);

        $workspace = Workspace::find($mem->workspace_id);

        return response()->json([
            'memory' => $mem->toPublicArray(revealSensitive: true),
            '_meta'  => [
                'workspace_id'   => $mem->workspace_id,
                'workspace_name' => $workspace?->name,
                'is_expired'     => $mem->isExpired(),
                'is_embedded'    => $mem->isEmbedded(),
                'embed_model'    => $mem->embedding_model,
                'hint'           => $mem->isExpired()
                    ? 'This memory is expired. It will not appear in search or list results. Update expires_at to reactivate.'
                    : null,
            ],
            'next_steps' => [
                ['action' => 'Update this memory', 'method' => 'PUT',    'endpoint' => "/api/v1/memory/{$id}"],
                ['action' => 'Delete this memory', 'method' => 'DELETE', 'endpoint' => "/api/v1/memory/{$id}"],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PUT /api/v1/memory/{id}
    // Update a memory — re-embeds automatically if content changes
    // ─────────────────────────────────────────────────────────────────────
    public function update(Request $request, string $id): JsonResponse
    {
        $apiKey        = $request->attributes->get('api_key');
        [$workspaceIds] = $this->resolveWorkspaceIds($apiKey);

        if (empty($workspaceIds)) {
            return $this->noWorkspaceError();
        }

        $mem = AgentMemory::whereIn('workspace_id', $workspaceIds)->findOrFail($id);

        $data = $request->validate([
            'workspace_id' => 'sometimes|uuid',
            'type'         => 'sometimes|in:credential,domain,ip,fact,config,note,skill,other',
            'label'        => 'sometimes|string|max:255',
            'content'      => 'sometimes|string',
            'value'        => 'sometimes|nullable|array',
            'tags'         => 'sometimes|nullable|array',
            'tags.*'       => 'string|max:50',
            'is_sensitive' => 'sometimes|boolean',
            'expires_at'   => 'sometimes|nullable|date',
        ]);

        // Validate workspace_id belongs to the same org
        if (!empty($data['workspace_id'])) {
            $targetWorkspace = Workspace::where('id', $data['workspace_id'])
                ->where('org_id', $apiKey->org_id)
                ->first();

            if (!$targetWorkspace) {
                return response()->json([
                    'error' => 'workspace_id does not belong to your organization.',
                    'code'  => 'invalid_workspace',
                ], 422);
            }
        }

        $contentChanged = isset($data['content']) && $data['content'] !== $mem->content;

        if ($contentChanged) {
            $embedText               = $this->memory->buildEmbedTextPublic(array_merge($mem->toArray(), $data));
            $embedding               = $this->embedder->embed($embedText);
            $data['embedding']       = $embedding;
            $data['embedding_model'] = $embedding ? $this->embedder->model() : null;
        }

        $data['last_updated_by'] = $apiKey->id;
        $mem->update($data);

        $this->events->record('memory.updated', 'memory', $mem->id, $apiKey, [
            'label'           => $mem->label,
            'content_changed' => $contentChanged,
            're_embedded'     => $contentChanged && isset($embedding) && $embedding !== null,
        ]);

        return response()->json([
            'status' => 'updated',
            'memory' => $mem->fresh(['creator', 'lastEditor'])->toPublicArray(),
            '_meta'  => [
                'content_changed' => $contentChanged,
                're_embedded'     => $contentChanged && !empty($data['embedding']),
                'embed_model'     => $mem->fresh()->embedding_model,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PUT /api/v1/memory/key/{key}
    // Upsert by named key — creates or updates transparently.
    // Pass workspace_id in body to target a specific workspace.
    // ─────────────────────────────────────────────────────────────────────
    public function upsertByKey(Request $request, string $key): JsonResponse
    {
        $apiKey    = $request->attributes->get('api_key');
        $workspace = $this->resolveTargetWorkspace($apiKey, $request->input('workspace_id'));

        if (!$workspace) {
            return $this->noWorkspaceError();
        }

        $data = $request->validate([
            'workspace_id' => 'nullable|uuid',
            'type'         => 'nullable|in:credential,domain,ip,fact,config,note,skill,other',
            'label'        => 'sometimes|string|max:255',
            'content'      => 'sometimes|string',
            'value'        => 'nullable|array',
            'tags'         => 'nullable|array',
            'tags.*'       => 'string|max:50',
            'is_sensitive' => 'nullable|boolean',
            'expires_at'   => 'nullable|date',
        ]);

        [$mem, $wasCreated] = $this->memory->upsertByKey($key, $data, $workspace->id, $apiKey);

        $total = AgentMemory::where('workspace_id', $workspace->id)->count();

        return response()->json([
            'status' => $wasCreated ? 'created' : 'updated',
            'memory' => $mem->toPublicArray(),
            '_meta'  => [
                'workspace_id'   => $workspace->id,
                'workspace_name' => $workspace->name,
                'total_memories' => $total,
                'embedded'       => $mem->isEmbedded(),
                'embed_model'    => $mem->embedding_model,
                'key'            => $key,
                'hint'           => "Future calls to PUT /api/v1/memory/key/{$key} will update this same record in workspace \"{$workspace->name}\".",
            ],
            'next_steps' => [
                ['action' => 'Retrieve by key', 'method' => 'GET',  'endpoint' => "/api/v1/memory?key={$key}"],
                ['action' => 'Semantic search', 'method' => 'POST', 'endpoint' => '/api/v1/memory/search', 'body' => ['q' => $mem->label]],
            ],
        ], $wasCreated ? 201 : 200);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/memory/{id}
    // Permanently remove a memory (org-scoped)
    // ─────────────────────────────────────────────────────────────────────
    public function destroy(Request $request, string $id): JsonResponse
    {
        $apiKey        = $request->attributes->get('api_key');
        [$workspaceIds] = $this->resolveWorkspaceIds($apiKey);

        if (empty($workspaceIds)) {
            return $this->noWorkspaceError();
        }

        $mem = AgentMemory::whereIn('workspace_id', $workspaceIds)->findOrFail($id);

        $snapshot = ['label' => $mem->label, 'type' => $mem->type, 'key' => $mem->memory_key];
        $mem->delete();

        $this->events->record('memory.deleted', 'memory', $id, $apiKey, $snapshot);

        return response()->json([
            'status'     => 'deleted',
            'memory_id'  => $id,
            '_meta'      => ['label' => $snapshot['label'], 'key' => $snapshot['key']],
            'next_steps' => [
                ['action' => 'List remaining memories', 'method' => 'GET',  'endpoint' => '/api/v1/memory'],
                ['action' => 'Store new memory',        'method' => 'POST', 'endpoint' => '/api/v1/memory'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/memory/search
    // Semantic vector search across the org's shared memory.
    // Optional: pass workspace_id in body to limit scope to one workspace.
    // Falls back to keyword search if Ollama is unreachable.
    // ─────────────────────────────────────────────────────────────────────
    public function search(Request $request): JsonResponse
    {
        $apiKey        = $request->attributes->get('api_key');
        [$workspaceIds, $scopeLabel] = $this->resolveWorkspaceIds($apiKey, $request->input('workspace_id'));

        if (empty($workspaceIds)) {
            return $this->noWorkspaceError();
        }

        $data = $request->validate([
            'q'            => 'required|string|min:2',
            'limit'        => 'nullable|integer|min:1|max:50',
            'type'         => 'nullable|string',
            'workspace_id' => 'nullable|uuid',
        ]);

        $limit  = $data['limit'] ?? 10;
        $result = $this->memory->search($data['q'], $workspaceIds, $limit);

        if (!$result['embedded']) {
            $fallback = $this->memory->keywordSearch($data['q'], $workspaceIds, $limit);

            return response()->json([
                'query'   => $data['q'],
                'mode'    => 'keyword_fallback',
                'results' => $fallback->map(fn($m) => [
                    'memory' => $m->toPublicArray(),
                    'score'  => null,
                    'rank'   => null,
                ])->values(),
                '_meta' => [
                    'scope'           => $scopeLabel,
                    'workspace_ids'   => $workspaceIds,
                    'embedded'        => false,
                    'results_returned'=> $fallback->count(),
                    'hint'            => 'Ollama embedding service was unreachable. Returned keyword matches instead. Semantic accuracy is reduced.',
                    'ollama_host'     => $this->embedder->host(),
                    'embed_model'     => $this->embedder->model(),
                ],
            ]);
        }

        $totalSearched = AgentMemory::whereIn('workspace_id', $workspaceIds)
            ->whereNotNull('embedding')
            ->count();

        $results = $result['results']->map(fn($r, $i) => [
            'memory' => $r['memory']->toPublicArray(),
            'score'  => $r['score'],
            'rank'   => $i + 1,
        ])->values();

        return response()->json([
            'query'   => $data['q'],
            'mode'    => 'semantic',
            'results' => $results,
            '_meta'   => [
                'scope'           => $scopeLabel,
                'workspace_ids'   => $workspaceIds,
                'embedded'        => true,
                'embed_model'     => $this->embedder->model(),
                'total_searched'  => $totalSearched,
                'results_returned'=> $results->count(),
                'hint'            => $results->isEmpty()
                    ? 'No memories matched this query. Try storing relevant context first.'
                    : 'Results sorted by semantic similarity (1.0 = identical, 0.0 = unrelated). Score ≥ 0.75 is a strong match.',
            ],
            'next_steps' => $results->isEmpty() ? [
                ['action' => 'Store a relevant memory', 'method' => 'POST', 'endpoint' => '/api/v1/memory'],
            ] : [
                ['action' => 'Get full detail (unmasked)', 'method' => 'GET', 'endpoint' => "/api/v1/memory/{$results->first()['memory']['id']}"],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Resolve all workspace IDs for the agent's org.
     * If $filterWorkspaceId is given and belongs to the org, return only that one.
     * Returns [string[] $ids, string $scopeLabel].
     */
    private function resolveWorkspaceIds($apiKey, ?string $filterWorkspaceId = null): array
    {
        $workspaces = Workspace::where('org_id', $apiKey->org_id)->get();

        if ($workspaces->isEmpty()) {
            return [[], 'org'];
        }

        if ($filterWorkspaceId) {
            $match = $workspaces->firstWhere('id', $filterWorkspaceId);
            if ($match) {
                return [[$match->id], "workspace:{$match->name}"];
            }
            // Invalid workspace_id — return empty so caller can 404/422
            return [[], 'unknown'];
        }

        return [$workspaces->pluck('id')->all(), 'org'];
    }

    /**
     * Resolve a single target workspace for write operations.
     * Uses $workspaceId if provided and valid for this org, otherwise picks the first workspace.
     */
    private function resolveTargetWorkspace($apiKey, ?string $workspaceId = null): ?Workspace
    {
        $query = Workspace::where('org_id', $apiKey->org_id);

        if ($workspaceId) {
            return $query->where('id', $workspaceId)->first();
        }

        return $query->orderBy('name')->first();
    }

    private function noWorkspaceError(): JsonResponse
    {
        return response()->json([
            'error' => 'No workspace found for this agent.',
            'code'  => 'no_workspace',
            'hint'  => 'Create a workspace first via POST /api/v1/organizations/{slug}/workspaces, then retry.',
        ], 422);
    }
}
