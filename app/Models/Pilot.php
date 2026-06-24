<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Pilot extends Model
{
    use HasUuids;

    protected $fillable = ['org_id', 'display_name', 'aliases', 'emails'];

    protected $casts = [
        'aliases' => 'array',
        'emails'  => 'array',
    ];
}
