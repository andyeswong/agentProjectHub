<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id', 'created_by', 'assignee_id',
        'title', 'description', 'status', 'priority',
        'due_date', 'start_date', 'estimated_hours', 'tags',
        'archived_at', 'archived_by', 'archive_reason',
    ];

    protected $casts = [
        'tags'            => 'array',
        'due_date'        => 'date',
        'start_date'      => 'date',
        'estimated_hours' => 'float',
        'archived_at'     => 'datetime',
    ];

    // Default scope: exclude archived tasks unless explicitly requested
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
