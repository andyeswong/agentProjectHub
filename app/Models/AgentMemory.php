<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMemory extends Model
{
    use HasUuids;

    protected $table = 'agent_memories';

    protected $fillable = [
        'workspace_id', 'created_by', 'last_updated_by',
        'memory_key', 'type', 'label', 'content',
        'value', 'tags', 'is_sensitive',
        'embedding', 'embedding_model',
        'expires_at',
    ];

    protected $casts = [
        'value'        => 'array',
        'tags'         => 'array',
        'embedding'    => 'array',
        'is_sensitive' => 'boolean',
        'expires_at'   => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'created_by');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'last_updated_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isEmbedded(): bool
    {
        return $this->embedding !== null;
    }

    /**
     * Return the memory without the raw embedding vector (too large for lists)
     * and with the value masked if sensitive.
     */
    public function toPublicArray(bool $revealSensitive = false): array
    {
        $data = $this->toArray();
        unset($data['embedding']); // Never expose raw vector in responses

        if ($this->is_sensitive && !$revealSensitive && isset($data['value'])) {
            $data['value'] = $this->maskSensitiveValue($data['value']);
        }

        $data['is_embedded'] = $this->isEmbedded();
        $data['is_expired']  = $this->isExpired();

        return $data;
    }

    private function maskSensitiveValue(array $value): array
    {
        return array_map(fn($v) => is_string($v) ? str_repeat('*', min(strlen($v), 8)) : '***', $value);
    }
}
