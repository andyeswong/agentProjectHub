<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A brand kit: tokens + voice + rules, plus linked assets. May extend another
 * brand via parent_id — resolution deep-merges the chain. See BrandService.
 */
class Brand extends Model
{
    use HasUuids;

    protected $table = 'brands';

    protected $fillable = [
        'workspace_id', 'slug', 'name', 'parent_id',
        'tokens', 'voice', 'rules', 'meta',
        'is_default', 'status', 'version',
        'created_by', 'last_updated_by',
    ];

    protected $casts = [
        'tokens'     => 'array',
        'voice'      => 'array',
        'rules'      => 'array',
        'meta'       => 'array',
        'is_default' => 'boolean',
        'version'    => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Assets this brand REFERENCES (it does not own them). */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'brand_assets')
            ->withPivot(['role', 'sort_order', 'is_primary'])
            ->withTimestamps();
    }
}
