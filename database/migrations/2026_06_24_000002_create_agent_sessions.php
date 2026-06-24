<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Episodic layer: a conversation/session record per agent run, tied to a pilot
 * so it can be resumed cross-agent. The warmup process lists recent+relevant
 * sessions on wake; resume returns a consolidated "where we left off" briefing.
 *
 * - external_id     : the host session id (e.g. Claude Code session uuid) — the
 *                     idempotency key for checkpoints from the same run.
 * - summary         : agent-written gist (compressed at checkpoint, not resume).
 * - embedding       : of the summary, for relevance ranking (cosine in PHP).
 * - open_threads    : unfinished work — the signal for "offer to resume".
 * - linked_memory_ids / linked_task_ids : associations into the semantic layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pilot_id')->nullable()->index();
            $table->uuid('api_key_id')->nullable()->index();
            $table->uuid('workspace_id')->nullable()->index();
            $table->string('external_id')->nullable()->index(); // host session id
            $table->string('agent_handle')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->json('embedding')->nullable();
            $table->json('open_threads')->nullable();
            $table->json('linked_memory_ids')->nullable();
            $table->json('linked_task_ids')->nullable();
            $table->string('status')->default('active'); // active | paused | done
            $table->string('cwd')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['api_key_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_sessions');
    }
};
