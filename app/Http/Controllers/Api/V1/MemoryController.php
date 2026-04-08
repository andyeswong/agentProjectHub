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
    // List all non-expired memories in the agent's workspace
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $apiKey    = $request->attributes->get('api_key');
        $workspace = $this->resolveWorkspace($apiKey);

        if (!$workspace) {
            return $this->noWorkspaceError();
        }

        $query = AgentMemory::where('workspace_id', $workspace->id)
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

        $total = AgentMemory::where('workspace_id', $workspace->id)->count();

        return response()->json([
            'data' => $memories->map(fn($m) => $m->toPublicArray())->values(),
            '_meta' => [
                'workspace_id'    => $workspace->id,
                'workspace_name'  => $workspace->name,
                'total_memories'  => $total,
                'returned'        => $memories->count(),
                'embed_model'     => $this->embedder->model(),
                'hint'            => 'Use POST /api/v1/memory/search for semantic (vector) search across memories.',
            ],
            'next_steps' => [
                ['action' => 'Semantic search',    'method' => 'POST', 'endpoint' => '/api/v1/memory/search', 'body' => ['q' => 'your query here']],
                ['action' => 'Store new memory',   'method' => 'POST', 'endpoint' => '/api/v1/memory'],
                ['action' => 'Upsert by key',      'method' => 'PUT',  'endpoint' => '/api/v1/memory/key/{key}'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/memory
    // Store a new memory — automatically embedded via mxbai-embed-large
    // ─────────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $apiKey    = $request->attributes->get('api_key');
        $workspace = $this->resolveWorkspace($apiKey);

        if (!$workspace) {
            return $this->noWorkspaceError();
        }

        $data = $request->validate([
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

        // If a key is given, check for conflict
        if (!empty($data['key'])) {
            $exists = AgentMemory::where('workspace_id', $workspace->id)
                ->where('memory_key', $data['key'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => "A memory with key \"{$data['key']}\" already exists in this workspace.",
                    'code'  => 'memory_key_conflict',
                    'hint'  => 'Use PUT /api/v1/memory/key/{key} to update an existing memory by key, or omit the key to create without one.',
                ], 409);
            }
        }

        $mem     = $this->memory->store($data, $workspace->id, $apiKey);
        $total   = AgentMemory::where('workspace_id', $workspace->id)->count();

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
                ['action' => 'Search memories',   'method' => 'POST', 'endpoint' => '/api/v1/memory/search', 'body' => ['q' => $mem->label]],
                ['action' => 'Retrieve this memory', 'method' => 'GET', 'endpoint' => "/api/v1/memory/{$mem->id}"],
                ['action' => 'List all memories', 'method' => 'GET',  'endpoint' => '/api/v1/memory'],
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/memory/{id}
    // Get a single memory with full (unmasked) value
    // ─────────────────────────────────────────────────────────────────────
    public function show(Request $request, string $id): JsonResponse
    {
        $apiKey    = $request->attributes->get('api_key');
        $workspace = $this->resolveWorkspace($apiKey);

        if (!$workspace) {
            return $this->noWorkspaceError();
        }

        $mem = AgentMemory::where('workspace_id', $workspace->id)
            ->with(['creator', 'lastEditor'])
            ->findOrFail($id);

        return response()->json([
            'memory' => $mem->toPublicArray(revealSensitive: true),
            '_meta'  => [
                'workspace_id'   => $workspace->id,
                'workspace_name' => $workspace->name,
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
        $apiKey    = $request->attributes->get('api_key');
        $workspace = $this->resolveWorkspace($apiKey);

        if (!$workspace) {
            return $this->noWorkspaceError();
        }

        $mem = AgentMemory::where('workspace_id', $workspace->id)->findOrFail($id);

        $data = $request->validate([
            'type'         => 'sometimes|in:credential,domain,ip,fact,config,note,skill,other',
            'label'        => 'sometimes|string|max:255',
            'content'      => 'sometimes|string',
            'value'        => 'sometimes|nullable|array',
            'tags'         => 'sometimes|nullable|array',
            'tags.*'       => 'string|max:50',
            'is_sensitive' => 'sometimes|boolean',
            'expires_at'   => 'sometimes|nullable|date',
        ]);

        $contentChanged = isset($data['content']) && $data['content'] !== $mem->content;

        if ($contentChanged) {
            $embedText       = $this->memory->buildEmbedTextPublic(array_merge($mem->toArray(), $data));
            $embedding       = $this->embedder->embed($embedText);
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
    // Upsert by named key — creates or updates transparently
    // ─────────────────────────────────────────────────────────────────────
    public function upsertByKey(Request $request, string $key): JsonResponse
    {
        $apiKey    = $request->attributes->get('api_key');
        $workspace = $this->resolveWorkspace($apiKey);

        if (!$workspace) {
            return $this->noWorkspaceError();
        }

        $data = $request->validate([
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
                'hint'           => "Future calls to PUT /api/v1/memory/key/{$key} will update this same record.",
            ],
            'next_steps' => [
                ['action' => 'Retrieve by key',   'method' => 'GET',  'endpoint' => "/api/v1/memory?key={$key}"],
                ['action' => 'Semantic search',   'method' => 'POST', 'endpoint' => '/api/v1/memory/search', 'body' => ['q' => $mem->label]],
            ],
        ], $wasCreated ? 201 : 200);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/memory/{id}
    // Permanently remove a memory
    // ─────────────────────────────────────────────────────────────────────
    public function destroy(Request $request, string $id): JsonResponse
    {
        $apiKey    = $request->attributes->get('api_key');
        $workspace = $this->resolveWorkspace($apiKey);

        if (!$workspace) {
            return $this->noWorkspaceError();
        }

        $mem = AgentMemory::where('workspace_id', $workspace->id)->findOrFail($id);

        $snapshot = ['label' => $mem->label, 'type' => $mem->type, 'key' => $mem->memory_key];
        $mem->delete();

        $this->events->record('memory.deleted', 'memory', $id, $apiKey, $snapshot);

        return response()->json([
            'status'   => 'deleted',
            'memory_id' => $id,
            '_meta'    => ['label' => $snapshot['label'], 'key' => $snapshot['key']],
            'next_steps' => [
                ['action' => 'List remaining memories', 'method' => 'GET',  'endpoint' => '/api/v1/memory'],
                ['action' => 'Store new memory',        'method' => 'POST', 'endpoint' => '/api/v1/memory'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/memory/search
    // Semantic vector search across the workspace's shared memory
    // Falls back to keyword search if Ollama is unreachable
    // ─────────────────────────────────────────────────────────────────────
    public function search(Request $request): JsonResponse
    {
        $apiKey    = $request->attributes->get('api_key');
        $workspace = $this->resolveWorkspace($apiKey);

        if (!$workspace) {
            return $this->noWorkspaceError();
        }

        $data  = $request->validate([
            'q'     => 'required|string|min:2',
            'limit' => 'nullable|integer|min:1|max:50',
            'type'  => 'nullable|string',
        ]);

        $limit  = $data['limit'] ?? 10;
        $result = $this->memory->search($data['q'], $workspace->id, $limit);

        if (!$result['embedded']) {
            // Ollama unreachable — keyword fallback
            $fallback = $this->memory->keywordSearch($data['q'], $workspace->id, $limit);

            return response()->json([
                'query'   => $data['q'],
                'mode'    => 'keyword_fallback',
                'results' => $fallback->map(fn($m) => [
                    'memory' => $m->toPublicArray(),
                    'score'  => null,
                    'rank'   => null,
                ])->values(),
                '_meta'   => [
                    'workspace_id'    => $workspace->id,
                    'workspace_name'  => $workspace->name,
                    'embedded'        => false,
                    'results_returned'=> $fallback->count(),
                    'hint'            => 'Ollama embedding service was unreachable. Returned keyword matches instead. Semantic accuracy is reduced.',
                    'ollama_host'     => $this->embedder->host(),
                    'embed_model'     => $this->embedder->model(),
                ],
            ]);
        }

        $totalSearched = AgentMemory::where('workspace_id', $workspace->id)
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
                'workspace_id'    => $workspace->id,
                'workspace_name'  => $workspace->name,
                'embedded'        => true,
                'embed_model'     => $this->embedder->model(),
                'total_searched'  => $totalSearched,
                'results_returned'=> $results->count(),
                'hint'            => $results->isEmpty()
                    ? 'No memories matched this query. Try storing relevant context first.'
                    : 'Results are sorted by semantic similarity (1.0 = identical, 0.0 = unrelated). Score ≥ 0.75 is a strong match.',
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
     * Resolve the workspace for the current API key.
     * Agents must have a workspace_id on their key, or we pick the first workspace in their org.
     */
    private function resolveWorkspace($apiKey): ?Workspace
    {
        if ($apiKey->workspace_id) {
            return Workspace::find($apiKey->workspace_id);
        }

        return Workspace::where('org_id', $apiKey->org_id)->first();
    }

    private function noWorkspaceError(): JsonResponse
    {
        return response()->json([
            'error' => 'No workspace found for this agent.',
            'code'  => 'no_workspace',
            'hint'  => 'Create a workspace first via POST /api/v1/organizations/{slug}/workspaces, then register or update your agent with the workspace_id.',
        ], 422);
    }
}
