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
     * Return the memory safe for public/list responses.
     * Sensitive memories have both `value` and `content` redacted.
     * Pass revealSensitive=true only from authenticated reveal endpoints.
     */
    public function toPublicArray(bool $revealSensitive = false): array
    {
        $data = $this->toArray();
        unset($data['embedding']); // Never expose raw vector in responses

        if ($this->is_sensitive && !$revealSensitive) {
            // Redact value — replace each field's value with asterisks
            if (isset($data['value']) && is_array($data['value'])) {
                $data['value'] = array_map(
                    fn($v) => is_string($v) ? str_repeat('*', min(strlen($v), 8)) : '***',
                    $data['value']
                );
            }

            // Redact content — may contain raw secrets the agent described
            $data['content'] = '[sensitive — click Reveal to view]';
        }

        $data['is_embedded'] = $this->isEmbedded();
        $data['is_expired']  = $this->isExpired();

        return $data;
    }
}
