<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Stop storing API keys in plaintext. Keys are random high-entropy strings
 * (sk_proj_<org>_<model>_<uuid>), so a sha256 of the key is a safe lookup token
 * (no rainbow-table risk). We keep a non-secret key_prefix for display (whoami)
 * and erase the plaintext column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key_hash', 64)->nullable()->after('key');
            $table->string('key_prefix')->nullable()->after('key_hash');
            $table->index('key_hash');
        });

        // The plaintext key is no longer stored, so it must become nullable.
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key')->nullable()->change();
        });

        // Phased rollout: backfill the hash + prefix now but KEEP the plaintext as
        // a recovery net (resolve can be reverted). A follow-up migration erases
        // the plaintext once hash auth is confirmed in production.
        foreach (DB::table('api_keys')->whereNotNull('key')->get() as $k) {
            DB::table('api_keys')->where('id', $k->id)->update([
                'key_hash'   => hash('sha256', $k->key),
                'key_prefix' => Str::substr($k->key, 0, 20),
            ]);
        }
    }

    public function down(): void
    {
        // Plaintext cannot be recovered from the hash; just drop the new columns.
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropIndex(['key_hash']);
            $table->dropColumn(['key_hash', 'key_prefix']);
        });
    }
};
