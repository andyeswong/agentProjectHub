<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// query_hits — how many times a memory surfaced in a memory_search result.
// Distinct from reinforced_count (re-integration). This is the "most consulted"
// signal powering the dashboard widget.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->unsignedInteger('query_hits')->default(0)->after('reinforced_count');
            $table->timestamp('last_queried_at')->nullable()->after('query_hits');
        });
    }

    public function down(): void
    {
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->dropColumn(['query_hits', 'last_queried_at']);
        });
    }
};
