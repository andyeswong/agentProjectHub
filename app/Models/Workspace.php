<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasUuids;

    protected $fillable = ['org_id', 'name', 'slug'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(AgentMemory::class);
    }
}
