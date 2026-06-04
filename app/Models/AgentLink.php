<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentLink extends Model
{
    use HasUuids;

    protected $fillable = [
        'org_id', 'initiator_id', 'target_id', 'status', 'intent',
        'requested_at', 'responded_at', 'opened_at', 'closed_at',
        'closed_by', 'close_reason', 'expires_at', 'last_activity_at',
    ];

    protected $casts = [
        'requested_at'     => 'datetime',
        'responded_at'     => 'datetime',
        'opened_at'        => 'datetime',
        'closed_at'        => 'datetime',
        'expires_at'       => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'initiator_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'target_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'link_id');
    }

    public function involves(string $agentId): bool
    {
        return $this->initiator_id === $agentId || $this->target_id === $agentId;
    }

    public function otherParty(string $agentId): ?string
    {
        if ($this->initiator_id === $agentId) {
            return $this->target_id;
        }
        if ($this->target_id === $agentId) {
            return $this->initiator_id;
        }
        return null;
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function toPublicArray(): array
    {
        return [
            'id'               => $this->id,
            'status'           => $this->status,
            'initiator_id'     => $this->initiator_id,
            'target_id'        => $this->target_id,
            'initiator_handle' => $this->relationLoaded('initiator') ? $this->initiator?->handle : null,
            'target_handle'    => $this->relationLoaded('target') ? $this->target?->handle : null,
            'intent'           => $this->intent,
            'requested_at'     => $this->requested_at,
            'opened_at'        => $this->opened_at,
            'closed_at'        => $this->closed_at,
            'close_reason'     => $this->close_reason,
            'expires_at'       => $this->expires_at,
            'last_activity_at' => $this->last_activity_at,
        ];
    }
}
