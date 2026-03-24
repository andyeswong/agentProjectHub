<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'event_type', 'entity_type', 'entity_id',
        'actor_api_key_id', 'actor_model', 'payload', 'ip_address',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'actor_api_key_id');
    }
}
