<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentMemory;
use App\Models\Workspace;
use App\Services\ActivityEventService;
use App\Services\ConsolidatorService;
use App\Services\EmbeddingService;
use App\Services\MemoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    public function __construct(
        private MemoryService $memory,
        private EmbeddingService $embedder,
        private ActivityEventService $events,
        private ConsolidatorService $consolidator,
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
            'workspace_id'        => 'nullable|uuid',
            'key'                 => 'nullable|string|max:255',
            'type'                => 'nullable|in:credential,domain,ip,fact,config,note,skill,other',
            'label'               => 'required|string|max:255',
            'content'             => 'required|string',
            'origin'              => 'nullable|string|max:500',
            'value'               => 'nullable|array',
            'tags'                => 'nullable|array',
            'tags.*'              => 'string|max:50',
            'associations'        => 'nullable|array',
            'associations.*.id'   => 'required|uuid',
            'associations.*.weight' => 'nullable|numeric',
            'associations.*.note' => 'nullable|string|max:255',
            'is_sensitive'        => 'nullable|boolean',
            'expires_at'          => 'nullable|date',
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

        // Revealing a sensitive value requires the elevated 'reveal_secrets'
        // capability (separate from plain 'read'). Every access to a sensitive
        // memory is audited — whether revealed or denied.
        $canReveal = $apiKey->hasPermission('reveal_secrets');

        if ($mem->is_sensitive) {
            $this->events->record(
                $canReveal ? 'secret.revealed' : 'secret.reveal_denied',
                'memory',
                $mem->id,
                $apiKey,
                ['label' => $mem->label, 'workspace_id' => $mem->workspace_id, 'memory_key' => $mem->memory_key],
                $request->ip(),
            );
        }

        return response()->json([
            'memory' => $mem->toPublicArray(revealSensitive: $canReveal),
            '_meta'  => [
                'workspace_id'   => $mem->workspace_id,
                'workspace_name' => $workspace?->name,
                'is_expired'     => $mem->isExpired(),
                'is_embedded'    => $mem->isEmbedded(),
                'embed_model'    => $mem->embedding_model,
                'associated'     => $this->resolveAssociations($mem, $workspaceIds),
                'reinforced_count' => $mem->reinforced_count,
                'revealed'       => $mem->is_sensitive ? $canReveal : null,
                'hint'           => $mem->is_sensitive && ! $canReveal
                    ? "This memory is sensitive and was returned MASKED — your API key lacks the 'reveal_secrets' capability. Ask your pilot to grant it."
                    : ($mem->isExpired()
                        ? 'This memory is expired. It will not appear in search or list results. Update expires_at to reactivate.'
                        : null),
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

        try {
            $mem->update($data);
        } catch (UniqueConstraintViolationException) {
            // A memory with the same memory_key already exists in the target workspace
            $conflicting = AgentMemory::where('workspace_id', $data['workspace_id'] ?? $mem->workspace_id)
                ->where('memory_key', $mem->memory_key)
                ->where('id', '!=', $mem->id)
                ->first();

            return response()->json([
                'error'      => "Cannot move memory: key \"{$mem->memory_key}\" already exists in the destination workspace.",
                'code'       => 'memory_key_conflict',
                'memory_key' => $mem->memory_key,
                'conflicting_memory' => $conflicting ? $conflicting->toPublicArray() : null,
                'hint'       => 'To resolve: (1) Delete the conflicting memory in the destination first, then retry the move. '
                              . '(2) Or clear the key on this memory first (PATCH with memory_key: null), then move it.',
                'resolution_options' => [
                    [
                        'option'   => 'delete_conflicting',
                        'action'   => 'Delete the existing memory in the destination, then retry',
                        'method'   => 'DELETE',
                        'endpoint' => $conflicting ? "/api/v1/memory/{$conflicting->id}" : null,
                    ],
                    [
                        'option'   => 'clear_key_then_move',
                        'action'   => 'Remove the key from this memory, then move it',
                        'step_1'   => ['method' => 'PUT', 'endpoint' => "/api/v1/memory/{$mem->id}", 'body' => ['memory_key' => null]],
                        'step_2'   => ['method' => 'PUT', 'endpoint' => "/api/v1/memory/{$mem->id}", 'body' => ['workspace_id' => $data['workspace_id'] ?? null]],
                    ],
                ],
            ], 409);
        }

        $this->events->record('memory.updated', 'memory', $mem->id, $apiKey, [
            'label'           => $mem->label,
            'content_changed' => $contentChanged,
            're_embedded'     => $contentChanged && isset($embedding) && $embedding !== null,
        ]);

        $fresh = $mem->fresh(['creator', 'lastEditor']);

        return response()->json([
            'status' => 'updated',
            'memory' => $fresh->toPublicArray(),
            '_meta'  => [
                'content_changed' => $contentChanged,
                're_embedded'     => $contentChanged && !empty($data['embedding']),
                'embed_model'     => $fresh->embedding_model,
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
            'spread'       => 'nullable|boolean',
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
        $directCount = $results->count();

        // Spreading activation: auto-fire memories strongly associated with the
        // direct hits (the "unconscious thoughts" that co-activate on recall).
        $spreadCount = 0;
        if ($request->boolean('spread', true)) {
            $seed   = $result['results']->pluck('memory');
            $rank   = $results->count();
            foreach ($this->memory->spreadActivate($seed, $workspaceIds) as $s) {
                $results->push([
                    'memory' => $s['memory']->toPublicArray(),
                    'score'  => round((float) $s['weight'], 4),
                    'rank'   => ++$rank,
                    'via'    => 'association',
                ]);
                $spreadCount++;
            }
        }

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
                'direct_hits'     => $directCount,
                'spread_activated'=> $spreadCount,
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
    // POST /api/v1/memory/{id}/integrate
    // COMPLEMENT a memory (append + record the correction/error-trail), NOT
    // overwrite. The original content is preserved; the note is appended and
    // logged in integration_log. Re-embeds, bumps reinforced_count. This is the
    // "memories integrate, not replace" operation (vs PUT which overwrites).
    // ─────────────────────────────────────────────────────────────────────
    public function integrate(Request $request, string $id): JsonResponse
    {
        $apiKey        = $request->attributes->get('api_key');
        [$workspaceIds] = $this->resolveWorkspaceIds($apiKey);

        if (empty($workspaceIds)) {
            return $this->noWorkspaceError();
        }

        $mem = AgentMemory::whereIn('workspace_id', $workspaceIds)->findOrFail($id);

        $data = $request->validate([
            'note'                  => 'required|string|min:1',
            'origin'                => 'nullable|string|max:500',
            'associations'          => 'nullable|array',
            'associations.*.id'     => 'required|uuid',
            'associations.*.weight' => 'nullable|numeric',
            'associations.*.note'   => 'nullable|string|max:255',
        ]);

        $fresh = $this->memory->integrate($mem, $data, $apiKey);

        return response()->json([
            'status' => 'integrated',
            'memory' => $fresh->toPublicArray(),
            '_meta'  => [
                'reinforced_count'  => $fresh->reinforced_count,
                'integration_count' => is_array($fresh->integration_log) ? count($fresh->integration_log) : 0,
                're_embedded'       => $fresh->isEmbedded(),
                'hint'              => 'Original content was PRESERVED; your note was appended and recorded in integration_log. Nothing was overwritten.',
            ],
            'next_steps' => [
                ['action' => 'View full history', 'method' => 'GET', 'endpoint' => "/api/v1/memory/{$id}"],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/memory/consolidate   ⚠️ EXPERIMENTAL
    // Same retrieval as /search, but pipes the matched memories through an
    // LLM knowledge-consolidator (config: services.consolidator, prod default
    // DeepSeek deepseek-v4-flash) and returns ONE consolidated KNOWLEDGE block
    // (rules + references + gotchas + provenance) instead of N raw memories.
    //
    // The normal /search path is untouched. Sensitive memory content is masked
    // BEFORE being sent to the external model (toPublicArray redaction), so raw
    // secrets never leave the box — they appear as [vault:mask] in the output.
    // ─────────────────────────────────────────────────────────────────────
    public function consolidate(Request $request): JsonResponse
    {
        $apiKey        = $request->attributes->get('api_key');
        [$workspaceIds, $scopeLabel] = $this->resolveWorkspaceIds($apiKey, $request->input('workspace_id'));

        if (empty($workspaceIds)) {
            return $this->noWorkspaceError();
        }

        $data = $request->validate([
            'q'            => 'required|string|min:2',
            'limit'        => 'nullable|integer|min:1|max:50',
            'workspace_id' => 'nullable|uuid',
            'model'        => 'nullable|string|max:64',
        ]);

        if (!$this->consolidator->enabled()) {
            return response()->json([
                'error'        => 'The knowledge consolidator is disabled or misconfigured on this server.',
                'code'         => 'consolidator_unavailable',
                'experimental' => true,
                'hint'         => 'Set CONSOLIDATOR_ENABLED=true plus CONSOLIDATOR_BASE_URL / CONSOLIDATOR_API_KEY / CONSOLIDATOR_MODEL in .env, then run php artisan config:clear. Until then, use POST /api/v1/memory/search.',
            ], 503);
        }

        $limit  = $data['limit'] ?? 10;
        $result = $this->memory->search($data['q'], $workspaceIds, $limit);

        // Retrieval (semantic, or keyword fallback if Ollama is down).
        $memories = $result['embedded']
            ? $result['results']->pluck('memory')
            : $this->memory->keywordSearch($data['q'], $workspaceIds, $limit);

        if ($memories->isEmpty()) {
            return response()->json([
                'query'        => $data['q'],
                'mode'         => 'consolidated',
                'experimental' => true,
                'knowledge'    => null,
                'provenance'   => [],
                '_meta'        => [
                    'scope'                 => $scopeLabel,
                    'memories_consolidated' => 0,
                    'hint'                  => 'No memories matched this query, so there was nothing to consolidate.',
                ],
            ]);
        }

        // Spreading activation: pull in strongly-associated memories so the
        // consolidation sees the co-activated context, not just the direct hits.
        $spreadCount = 0;
        foreach ($this->memory->spreadActivate($memories, $workspaceIds) as $s) {
            if (!$memories->contains('id', $s['memory']->id)) {
                $memories->push($s['memory']);
                $spreadCount++;
            }
        }

        // Build the MASKED raw block. Sensitive content is already redacted by
        // toPublicArray() — secrets never reach the external LLM.
        $blocks = [];
        $provenance = [];
        $rawChars = 0;
        foreach ($memories as $m) {
            $pub = $m->toPublicArray();
            $content = $pub['content'] ?? '';
            $blocks[] = "### [id: {$m->id}] ({$m->type}) {$m->label}\n{$content}";
            $rawChars += mb_strlen($m->label) + mb_strlen($content);
            $provenance[] = [
                'id'    => $m->id,
                'key'   => $m->memory_key,
                'type'  => $m->type,
                'label' => $m->label,
            ];
        }
        $maskedRaw = implode("\n\n", $blocks);

        $out = $this->consolidator->consolidate($maskedRaw, $data['q'], $data['model'] ?? null);

        if (!$out['ok']) {
            return response()->json([
                'error'        => $out['error'] ?? 'Consolidation failed.',
                'code'         => 'consolidation_failed',
                'experimental' => true,
                'hint'         => 'Retrieval succeeded but the consolidator LLM call failed. The raw memories are still available via POST /api/v1/memory/search.',
            ], 502);
        }

        // Citation guard: reasoning models sometimes leak chain-of-thought INTO
        // a [src: …] bracket (e.g. "[src: id? actually from … better cite …]")
        // or cite ids that were never retrieved. Rewrite every citation to hold
        // ONLY valid, comma-separated provenance ids — deterministic, model-agnostic.
        $validIds  = array_column($provenance, 'id');
        $rawKnowledge = $out['knowledge'];
        $knowledge = $this->sanitizeCitations($rawKnowledge, $validIds);
        $citationsCleaned = $knowledge !== $rawKnowledge;
        $knowledgeChars = mb_strlen($knowledge);
        // Cheap server-side token estimate (chars/4). The LLM usage block, when
        // the provider returns it, carries the real prompt/completion counts.
        $rawTok = (int) round($rawChars / 4);
        $knTok  = (int) round($knowledgeChars / 4);

        $this->events->record('memory.consolidated', 'workspace', $workspaceIds[0], $apiKey, [
            'query'                 => $data['q'],
            'memories_consolidated' => $memories->count(),
            'source_memory_ids'     => array_column($provenance, 'id'),
            'model'                 => $out['model'],
            'scope'                 => $scopeLabel,
        ]);

        return response()->json([
            'query'        => $data['q'],
            'mode'         => 'consolidated',
            'experimental' => true,
            'knowledge'    => $knowledge,
            'provenance'   => $provenance,
            '_meta'        => [
                'scope'                 => $scopeLabel,
                'workspace_ids'         => $workspaceIds,
                'retrieval'             => $result['embedded'] ? 'semantic' : 'keyword_fallback',
                'memories_consolidated' => $memories->count(),
                'spread_activated'      => $spreadCount,
                'consolidator_model'    => $out['model'],
                'llm_usage'             => $out['usage'],
                'raw_token_estimate'    => $rawTok,
                'knowledge_token_estimate' => $knTok,
                'reduction_pct'         => $rawTok > 0 ? round(100 * ($rawTok - $knTok) / $rawTok, 1) : null,
                'citations_cleaned'     => $citationsCleaned,
                'warning'               => 'EXPERIMENTAL. Consolidation is LOSSY by design — it drops examples and restated detail to produce applicable rules. Treat output as a CANDIDATE for human review, not a replacement for the source memories (kept in provenance).',
            ],
            'next_steps' => [
                ['action' => 'Inspect a source memory', 'method' => 'GET',  'endpoint' => "/api/v1/memory/{$provenance[0]['id']}"],
                ['action' => 'Get raw (unconsolidated)', 'method' => 'POST', 'endpoint' => '/api/v1/memory/search', 'body' => ['q' => $data['q']]],
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

    /**
     * Rewrite every [src: …] citation so it holds ONLY valid, comma-separated
     * provenance ids. Strips leaked reasoning / hedging / invalid ids that some
     * models emit inside the bracket. Deterministic, model-agnostic.
     */
    private function sanitizeCitations(string $knowledge, array $validIds): string
    {
        $valid = array_flip($validIds);

        return preg_replace_callback('/\[src:[^\]]*\]/i', function ($m) use ($valid) {
            // Pull any UUID-shaped tokens out of the bracket, keep only known ones.
            preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $m[0], $found);
            $kept = array_values(array_unique(array_filter($found[0], fn ($id) => isset($valid[$id]))));

            return empty($kept) ? '[src: unverified]' : '[src: ' . implode(', ', $kept) . ']';
        }, $knowledge);
    }

    /**
     * Spreading activation (light): resolve a memory's association edges to
     * {id, label, type, weight}, strongest first. These are the "associated
     * thoughts" that co-activate when this memory is retrieved.
     */
    private function resolveAssociations(AgentMemory $mem, array $workspaceIds): array
    {
        $assoc = $mem->associations;
        if (empty($assoc) || !is_array($assoc)) {
            return [];
        }

        $byId = [];
        foreach ($assoc as $a) {
            if (!empty($a['id'])) {
                $byId[$a['id']] = (float) ($a['weight'] ?? 1.0);
            }
        }
        if (empty($byId)) {
            return [];
        }

        $targets = AgentMemory::whereIn('workspace_id', $workspaceIds)
            ->whereIn('id', array_keys($byId))
            ->get(['id', 'memory_key', 'type', 'label']);

        $out = $targets->map(fn ($t) => [
            'id'     => $t->id,
            'key'    => $t->memory_key,
            'type'   => $t->type,
            'label'  => $t->label,
            'weight' => $byId[$t->id] ?? 1.0,
        ])->sortByDesc('weight')->values()->all();

        return $out;
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
