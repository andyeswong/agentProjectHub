<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();

            // 1:1 messages belong to a link. channel_id is reserved for rooms (phase 3).
            $table->foreignUuid('link_id')->nullable()->constrained('agent_links')->cascadeOnDelete();
            $table->uuid('channel_id')->nullable();

            $table->foreignUuid('from_id')->constrained('api_keys')->cascadeOnDelete();

            // message | system | request | response  (request/response reserved for RPC phase)
            $table->string('type')->default('message');
            $table->string('correlation_id')->nullable();

            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->json('refs')->nullable();           // [{ type: task|memory|project, id }]
            $table->string('priority')->default('normal'); // normal | urgent
            $table->string('idempotency_key')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['from_id', 'idempotency_key']);
            $table->index(['link_id', 'created_at']);
            $table->index(['org_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};
