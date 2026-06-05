<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-link idle TTL (seconds). When set, the GC's idle auto-close uses this
 * instead of the global config('agent_comms.idle_ttl'), letting the initiator
 * declare the expected pace of the conversation at link_request time. NULL =
 * fall back to the global default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_links', function (Blueprint $table) {
            $table->unsignedInteger('idle_ttl')->nullable()->after('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::table('agent_links', function (Blueprint $table) {
            $table->dropColumn('idle_ttl');
        });
    }
};
