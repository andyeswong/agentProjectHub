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
    ];

    protected $casts = [
        'tags'             => 'array',
        'due_date'         => 'date',
        'start_date'       => 'date',
        'estimated_hours'  => 'float',
    ];

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
