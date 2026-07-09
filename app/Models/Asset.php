<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored binary asset (logo, screenshot, moodboard, reference). Workspace
 * scoped. The bytes live on a Storage disk; this row is metadata + an embedded
 * description for semantic search. See the create_assets_table migration for
 * the F1/F2 decoupling contract.
 */
class Asset extends Model
{
    use HasUuids;

    protected $table = 'assets';

    protected $fillable = [
        'workspace_id',
        'kind',
        'filename',
        'mime_type',
        'size',
        'width',
        'height',
        'storage_disk',
        'storage_key',
        'checksum',
        'description',
        'embedding',
        'embedding_model',
        'tags',
        'brand_hint',
        'created_by',
    ];

    protected $casts = [
        'embedding' => 'array',
        'tags'      => 'array',
        'size'      => 'integer',
        'width'     => 'integer',
        'height'    => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isEmbedded(): bool
    {
        return is_array($this->embedding) && count($this->embedding) > 0;
    }

    /** Absolute URL to fetch the raw bytes (auth-scoped stream endpoint). */
    public function rawUrl(): string
    {
        return url("/api/v1/assets/{$this->id}/raw");
    }

    /**
     * Public serialization — never leaks the raw embedding vector; surfaces a
     * raw_url pointer + embed/status flags. Mirrors AgentMemory::toPublicArray.
     */
    public function toPublicArray(): array
    {
        $data = $this->toArray();
        unset($data['embedding']); // never expose the raw vector

        $data['raw_url']     = $this->rawUrl();
        $data['is_embedded'] = $this->isEmbedded();

        return $data;
    }
}
