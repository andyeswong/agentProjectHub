<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPresence extends Model
{
    protected $table = 'agent_presence';
    protected $primaryKey = 'agent_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'agent_id', 'status', 'available_since', 'last_heartbeat', 'meta',
    ];

    protected $casts = [
        'available_since' => 'datetime',
        'last_heartbeat'  => 'datetime',
        'meta'            => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'agent_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
