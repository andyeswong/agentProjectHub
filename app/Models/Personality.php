<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One layer of a personality cascade (core | runtime | channel).
 * A full self is the set of rows sharing (workspace_id, slug); resolve()
 * in PersonalityService walks core -> runtime -> channel and merges them.
 */
class Personality extends Model
{
    use HasUuids;

    protected $table = 'personalities';

    protected $fillable = [
        'workspace_id', 'slug', 'name', 'parent_id', 'level',
        'match_client_type', 'match_channel',
        'soul', 'register', 'model_pref', 'scopes', 'tools', 'rules', 'refs', 'meta',
        'status', 'version', 'created_by', 'last_updated_by',
    ];

    protected $casts = [
        'scopes'  => 'array',
        'tools'   => 'array',
        'rules'   => 'array',
        'refs'    => 'array',
        'meta'    => 'array',
        'version' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Personality::class, 'parent_id');
    }
}
