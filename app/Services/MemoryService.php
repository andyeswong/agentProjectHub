<?php

namespace App\Services;

use App\Models\AgentMemory;
use App\Models\ApiKey;
use Illuminate\Support\Facades\DB;

class MemoryService
{
    public function __construct(
        private EmbeddingService $embedder,
        private ActivityEventService $events,
    ) {}

    /**
     * Store a new memory with automatic embedding.
     */
    public function store(array $data, string $workspaceId, ApiKey $actor): AgentMemory
    {
        $embedding      = $this->embedder->embed($this->buildEmbedText($data));
        $embeddingModel = $embedding ? $this->embedder->model() : null;

        $memory = AgentMemory::create([
            'workspace_id'    => $workspaceId,
            'created_by'      => $actor->id,
            'last_updated_by' => $actor->id,
            'memory_key'      => $data['key'] ?? null,
            'type'            => $data['type'] ?? 'fact',
            'label'           => $data['label'],
            'content'         => $data['content'],
            'origin'          => $data['origin'] ?? null,
            'value'           => $data['value'] ?? null,
            'tags'            => $data['tags'] ?? null,
            'associations'    => $data['associations'] ?? null,
            'is_sensitive'    => $data['is_sensitive'] ?? false,
            'embedding'       => $embedding,
            'embedding_model' => $embeddingModel,
            'expires_at'      => $data['expires_at'] ?? null,
        ]);

        $this->events->record(
            'memory.stored',
            'memory',
            $memory->id,
            $actor,
            ['label' => $memory->label, 'type' => $memory->type, 'key' => $memory->memory_key],
        );

        return $memory;
    }

    /**
     * Upsert a memory by named key within a workspace.
     * Creates if not found, updates if exists.
     */
    public function upsertByKey(string $key, array $data, string $workspaceId, ApiKey $actor): array
    {
        $existing = AgentMemory::where('workspace_id', $workspaceId)
            ->where('memory_key', $key)
            ->first();

        $contentChanged = !$existing || ($existing->content !== ($data['content'] ?? $existing->content));

        $embedding      = $contentChanged ? $this->embedder->embed($this->buildEmbedText($data)) : $existing->embedding;
        $embeddingModel = $embedding ? $this->embedder->model() : ($existing->embedding_model ?? null);

        $attributes = [
            'workspace_id'    => $workspaceId,
            'created_by'      => $existing ? $existing->created_by : $actor->id,
            'last_updated_by' => $actor->id,
            'memory_key'      => $key,
            'type'            => $data['type']        ?? ($existing->type         ?? 'fact'),
            'label'           => $data['label']       ?? ($existing->label        ?? $key),
            'content'         => $data['content']     ?? ($existing->content      ?? ''),
            'value'           => $data['value']       ?? ($existing->value        ?? null),
            'tags'            => $data['tags']        ?? ($existing->tags         ?? null),
            'is_sensitive'    => $data['is_sensitive'] ?? ($existing->is_sensitive ?? false),
            'embedding'       => $embedding,
            'embedding_model' => $embeddingModel,
            'expires_at'      => array_key_exists('expires_at', $data) ? $data['expires_at'] : ($existing->expires_at ?? null),
        ];

        if ($existing) {
            $existing->update($attributes);
            $memory = $existing->fresh();
            $wasCreated = false;

            $this->events->record('memory.updated', 'memory', $memory->id, $actor, [
                'label' => $memory->label, 'type' => $memory->type, 'key' => $key,
            ]);
        } else {
            $memory = AgentMemory::create($attributes);
            $wasCreated = true;

            $this->events->record('memory.stored', 'memory', $memory->id, $actor, [
                'label' => $memory->label, 'type' => $memory->type, 'key' => $key,
            ]);
        }

        return [$memory, $wasCreated];
    }

    /**
     * Integrate (COMPLEMENT, never replace) new info into an existing memory.
     * The original content is PRESERVED — the note is appended and recorded in
     * integration_log (the error-trail / correction history). Re-embeds and
     * bumps reinforced_count (the repetition/consolidation signal).
     */
    public function integrate(AgentMemory $memory, array $data, ApiKey $actor): AgentMemory
    {
        $note  = trim($data['note']);
        $stamp = now()->toIso8601String();

        // PRESERVE the original — append, do not overwrite.
        $appended = rtrim($memory->content) . "\n\n[integrated {$stamp}] {$note}";

        $log   = $memory->integration_log ?? [];
        $log[] = ['at' => $stamp, 'by' => $actor->id, 'note' => $note];

        // Merge association edges by target id (last weight wins).
        $merged = $memory->associations ?? [];
        if (!empty($data['associations']) && is_array($data['associations'])) {
            $byId = [];
            foreach (array_merge($merged, $data['associations']) as $a) {
                if (!empty($a['id'])) {
                    $byId[$a['id']] = $a;
                }
            }
            $merged = array_values($byId);
        }

        $embedding = $this->embedder->embed($this->buildEmbedText([
            'type'    => $memory->type,
            'label'   => $memory->label,
            'content' => $appended,
            'tags'    => $memory->tags,
        ]));

        $memory->update([
            'content'          => $appended,
            'origin'           => $data['origin'] ?? $memory->origin,
            'associations'     => $merged ?: null,
            'integration_log'  => $log,
            'reinforced_count' => ($memory->reinforced_count ?? 0) + 1,
            'last_updated_by'  => $actor->id,
            'embedding'        => $embedding,
            'embedding_model'  => $embedding ? $this->embedder->model() : $memory->embedding_model,
        ]);

        $fresh = $memory->fresh(['creator', 'lastEditor']);

        $this->events->record('memory.integrated', 'memory', $memory->id, $actor, [
            'label'            => $fresh->label,
            'note'             => mb_substr($note, 0, 200),
            'reinforced_count' => $fresh->reinforced_count,
        ]);

        return $fresh;
    }

    /**
     * Semantic search across memories.
     * Pass an array of workspace IDs to search (org-wide = all workspaces).
     * Pass a single workspace ID string to search within one workspace.
     */
    public function search(string $query, array|string $workspaceIds, int $limit = 10): array
    {
        $queryVector = $this->embedder->embed($query);
        $ids         = (array) $workspaceIds;

        if (!$queryVector) {
            return ['results' => [], 'embedded' => false, 'fallback' => 'keyword'];
        }

        // Postgres + pgvector: indexed ANN search via the HNSW index (embedding_vec
        // <=> query). Floats are cast so the literal is injection-safe and the
        // ORDER BY expression matches the index for it to be used.
        if (DB::connection()->getDriverName() === 'pgsql') {
            $literal = "'[" . implode(',', array_map(fn ($f) => (float) $f, $queryVector)) . "]'";

            // Select every column EXCEPT the bulky embedding_vec (never returned to
            // clients); keep `embedding` so isEmbedded()/toPublicArray behave as before.
            $rows = AgentMemory::query()
                ->whereIn('workspace_id', $ids)
                ->whereNotNull('embedding_vec')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->select([
                    'id', 'workspace_id', 'created_by', 'last_updated_by', 'memory_key',
                    'type', 'label', 'content', 'origin', 'value', 'tags',
                    'associations', 'integration_log', 'reinforced_count', 'is_sensitive',
                    'embedding', 'embedding_model', 'expires_at', 'created_at', 'updated_at',
                ])
                ->selectRaw("(embedding_vec <=> {$literal}::vector) AS distance")
                ->orderByRaw("embedding_vec <=> {$literal}::vector")
                ->limit($limit)
                ->get();

            $scored = $rows->map(fn (AgentMemory $m) => [
                'memory' => $m,
                'score'  => round(1 - (float) $m->distance, 6),
            ])->values();

            return ['results' => $scored, 'embedded' => true, 'fallback' => null];
        }

        // Fallback (sqlite tests / non-pgvector): load embeddings and cosine in PHP.
        $memories = AgentMemory::whereIn('workspace_id', $ids)
            ->whereNotNull('embedding')
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();

        $scored = $memories
            ->map(fn(AgentMemory $m) => [
                'memory' => $m,
                'score'  => $this->embedder->cosineSimilarity($queryVector, $m->embedding),
            ])
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return [
            'results'  => $scored,
            'embedded' => true,
            'fallback' => null,
        ];
    }

    /**
     * Keyword fallback search for when Ollama is unreachable.
     */
    public function keywordSearch(string $query, array|string $workspaceIds, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $ids = (array) $workspaceIds;

        return AgentMemory::whereIn('workspace_id', $ids)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn($q) =>
                $q->where('label', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%")
                  ->orWhere('memory_key', 'like', "%{$query}%")
            )
            ->limit($limit)
            ->get();
    }

    /**
     * Spreading activation: given seed memories, auto-fire their strongly-weighted
     * association edges and return the linked memories NOT already in the seed.
     * This is the "unconscious thought" — retrieving X co-activates its associated
     * thoughts. Edge weight >= $threshold fires; capped to avoid runaway spread.
     *
     * @param  \Illuminate\Support\Collection<AgentMemory>  $seed
     * @return \Illuminate\Support\Collection<array{memory:AgentMemory, weight:float}>
     */
    public function spreadActivate($seed, array $workspaceIds, float $threshold = 0.5, int $cap = 10): \Illuminate\Support\Collection
    {
        $seedIds = $seed->pluck('id')->all();
        $wanted  = []; // target id => strongest incoming edge weight

        foreach ($seed as $m) {
            foreach (($m->associations ?? []) as $a) {
                if (empty($a['id']) || in_array($a['id'], $seedIds, true)) {
                    continue;
                }
                $w = (float) ($a['weight'] ?? 1.0);
                if ($w < $threshold) {
                    continue;
                }
                $wanted[$a['id']] = max($wanted[$a['id']] ?? 0.0, $w);
            }
        }

        if (empty($wanted)) {
            return collect();
        }

        arsort($wanted);
        $ids = array_slice(array_keys($wanted), 0, $cap);

        $rows = AgentMemory::whereIn('workspace_id', $workspaceIds)
            ->whereIn('id', $ids)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();

        return $rows
            ->map(fn (AgentMemory $m) => ['memory' => $m, 'weight' => $wanted[$m->id] ?? 1.0])
            ->sortByDesc('weight')
            ->values();
    }

    /**
     * Build the text that gets embedded — combines label + content + type for richer context.
     * Public so controllers can re-embed on update without duplicating logic.
     */
    public function buildEmbedTextPublic(array $data): string
    {
        return $this->buildEmbedText($data);
    }

    private function buildEmbedText(array $data): string
    {
        $parts = [];

        if (!empty($data['type'])) {
            $parts[] = "Type: {$data['type']}";
        }
        if (!empty($data['label'])) {
            $parts[] = "Label: {$data['label']}";
        }
        if (!empty($data['content'])) {
            $parts[] = $data['content'];
        }
        if (!empty($data['origin'])) {
            $parts[] = "Origin: {$data['origin']}";
        }
        if (!empty($data['tags'])) {
            $parts[] = 'Tags: ' . implode(', ', (array) $data['tags']);
        }

        return implode('. ', $parts);
    }
}
