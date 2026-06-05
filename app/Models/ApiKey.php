<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiKey extends Model
{
    use HasUuids;

    protected $fillable = [
        'key', 'key_hash', 'key_prefix', 'org_id', 'workspace_id', 'owner_type',
        'model', 'model_provider', 'client_type', 'handle',
        'pilot', 'pilot_contact', 'permissions', 'rate_limit',
        'system_prompt_hash', 'metadata', 'last_active_at', 'revoked_at',
    ];

    protected $casts = [
        'permissions'    => 'array',
        'metadata'       => 'array',
        'last_active_at' => 'datetime',
        'revoked_at'     => 'datetime',
    ];

    protected $hidden = ['key', 'key_hash'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function pilotTokens(): HasMany
    {
        return $this->hasMany(PilotToken::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function activityEvents(): HasMany
    {
        return $this->hasMany(ActivityEvent::class, 'actor_api_key_id');
    }

    public function memories(): HasMany
    {
        return $this->hasMany(\App\Models\AgentMemory::class, 'created_by');
    }

    public function presence(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AgentPresence::class, 'agent_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }
}
