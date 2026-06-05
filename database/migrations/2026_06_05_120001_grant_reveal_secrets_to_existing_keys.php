<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Non-breaking rollout of the new 'reveal_secrets' capability. memory_get now
 * requires it to return an unmasked sensitive value; grant it to every existing
 * key so current agents keep the access they already had. New keys do NOT get
 * it by default (least privilege) — pilots grant it explicitly and should review
 * and strip it from keys that don't need it.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('api_keys')->get() as $k) {
            $perms = json_decode($k->permissions ?? '[]', true) ?: [];
            if (! in_array('reveal_secrets', $perms, true)) {
                $perms[] = 'reveal_secrets';
                DB::table('api_keys')->where('id', $k->id)->update(['permissions' => json_encode($perms)]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('api_keys')->get() as $k) {
            $perms = json_decode($k->permissions ?? '[]', true) ?: [];
            if (($i = array_search('reveal_secrets', $perms, true)) !== false) {
                unset($perms[$i]);
                DB::table('api_keys')->where('id', $k->id)->update(['permissions' => json_encode(array_values($perms))]);
            }
        }
    }
};
