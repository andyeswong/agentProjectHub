<?php

namespace App\Services;

use App\Models\AgentMemory;
use App\Models\ApiKey;

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
            'value'           => $data['value'] ?? null,
            'tags'            => $data['tags'] ?? null,
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
     * Semantic search across all embedded memories in a workspace.
     * Returns memories sorted by cosine similarity descending.
     */
    public function search(string $query, string $workspaceId, int $limit = 10): array
    {
        $queryVector = $this->embedder->embed($query);

        if (!$queryVector) {
            return ['results' => [], 'embedded' => false, 'fallback' => 'keyword'];
        }

        // Load all embedded, non-expired memories in this workspace
        $memories = AgentMemory::where('workspace_id', $workspaceId)
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
    public function keywordSearch(string $query, string $workspaceId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return AgentMemory::where('workspace_id', $workspaceId)
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
        if (!empty($data['tags'])) {
            $parts[] = 'Tags: ' . implode(', ', (array) $data['tags']);
        }

        return implode('. ', $parts);
    }
}
