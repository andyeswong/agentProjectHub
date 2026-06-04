<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'org_id', 'link_id', 'channel_id', 'from_id', 'type', 'correlation_id',
        'body', 'meta', 'refs', 'priority', 'idempotency_key', 'read_at',
    ];

    protected $casts = [
        'meta'    => 'array',
        'refs'    => 'array',
        'read_at' => 'datetime',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(AgentLink::class, 'link_id');
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'from_id');
    }

    public function toPublicArray(): array
    {
        return [
            'id'             => $this->id,
            'link_id'        => $this->link_id,
            'from_id'        => $this->from_id,
            'from_handle'    => $this->relationLoaded('from') ? $this->from?->handle : null,
            'type'           => $this->type,
            'correlation_id' => $this->correlation_id,
            'body'           => $this->body,
            'meta'           => $this->meta,
            'refs'           => $this->refs,
            'priority'       => $this->priority,
            'read_at'        => $this->read_at,
            'created_at'     => $this->created_at,
        ];
    }
}
