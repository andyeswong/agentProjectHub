<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rich memory model: integration (no-replace) + associations + origin.
 *
 * - origin            : episodic provenance tag ("learned from X / kindergarten")
 * - associations      : weighted edges to other memories [{id, weight, note}]
 *                       (spreading-activation; auto-fire candidates by weight)
 * - integration_log   : append-only history of complements/corrections
 *                       [{at, by, note}] — the error-trail, kept not overwritten
 * - reinforced_count  : how many times this memory was re-encountered/integrated
 *                       (the consolidation / repetition signal)
 *
 * All additive + nullable → safe online migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->text('origin')->nullable()->after('content');
            $table->json('associations')->nullable()->after('tags');
            $table->json('integration_log')->nullable()->after('associations');
            $table->unsignedInteger('reinforced_count')->default(0)->after('integration_log');
        });
    }

    public function down(): void
    {
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->dropColumn(['origin', 'associations', 'integration_log', 'reinforced_count']);
        });
    }
};
