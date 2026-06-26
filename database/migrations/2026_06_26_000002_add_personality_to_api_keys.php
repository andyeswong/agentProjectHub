<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bind a body (api_key / runtime) to the self it wears. Many keys -> one self:
 * the Claude Code key and every OpenClaw key can all carry personality_slug
 * = 'maia'. The channel is supplied per-request at resolve time (a body knows
 * which channel it's speaking on); only the self binding lives on the key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('personality_slug')->nullable()->after('client_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('personality_slug');
        });
    }
};
