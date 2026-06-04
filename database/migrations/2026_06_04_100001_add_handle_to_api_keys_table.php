<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            // Human-readable handle agents address each other by, unique within an org.
            $table->string('handle')->nullable()->after('client_type');
            $table->unique(['org_id', 'handle']);
        });

        // Backfill: derive a unique-per-org handle from the model name and grant
        // the new 'comms' capability to existing agents so they can opt in.
        $taken = [];
        foreach (DB::table('api_keys')->get() as $k) {
            $base = Str::slug($k->model ?: $k->client_type ?: 'agent') ?: 'agent';
            $taken[$k->org_id] ??= [];
            $handle = $base;
            $i = 1;
            while (in_array($handle, $taken[$k->org_id], true)) {
                $handle = $base . '-' . (++$i);
            }
            $taken[$k->org_id][] = $handle;

            $perms = json_decode($k->permissions ?? '[]', true) ?: [];
            if (! in_array('comms', $perms, true)) {
                $perms[] = 'comms';
            }

            DB::table('api_keys')->where('id', $k->id)->update([
                'handle'      => $handle,
                'permissions' => json_encode($perms),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropUnique(['org_id', 'handle']);
            $table->dropColumn('handle');
        });
    }
};
