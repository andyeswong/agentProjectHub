<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AgentSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'pilot_id', 'api_key_id', 'workspace_id', 'external_id', 'agent_handle',
        'title', 'summary', 'embedding', 'open_threads',
        'linked_memory_ids', 'linked_task_ids',
        'status', 'cwd', 'started_at', 'last_active_at', 'ended_at',
    ];

    protected $casts = [
        'embedding'         => 'array',
        'open_threads'      => 'array',
        'linked_memory_ids' => 'array',
        'linked_task_ids'   => 'array',
        'started_at'        => 'datetime',
        'last_active_at'    => 'datetime',
        'ended_at'          => 'datetime',
    ];

    protected $hidden = ['embedding'];

    public function hasOpenThreads(): bool
    {
        return !empty($this->open_threads);
    }

    public function toPublicArray(): array
    {
        $data = $this->toArray();
        unset($data['embedding']);
        return $data;
    }
}
