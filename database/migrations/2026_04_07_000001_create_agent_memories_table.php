<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('api_keys');
            $table->foreignUuid('last_updated_by')->nullable()->constrained('api_keys')->nullOnDelete();

            // Named key for direct retrieval — unique within a workspace
            $table->string('memory_key')->nullable();

            // Type of memory for filtering and context
            $table->enum('type', [
                'credential',   // passwords, tokens, secrets
                'domain',       // domain names, URLs
                'ip',           // IP addresses, network info
                'fact',         // general facts about the project/environment
                'config',       // configuration values
                'note',         // freeform notes
                'other',
            ])->default('fact');

            // Human/agent-readable label describing what this memory is
            $table->string('label');

            // The text used for embedding and semantic search
            $table->text('content');

            // Structured data (e.g., {username, password, url} for credentials)
            $table->json('value')->nullable();

            $table->json('tags')->nullable();

            // Sensitive memories mask their value in list responses
            $table->boolean('is_sensitive')->default(false);

            // Vector embedding from mxbai-embed-large — stored as JSON float array
            // null if Ollama was unreachable at store time
            $table->json('embedding')->nullable();

            // Which model/provider generated the embedding
            $table->string('embedding_model')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // A workspace can have one memory per named key
            $table->unique(['workspace_id', 'memory_key']);

            $table->index(['workspace_id', 'type']);
            $table->index(['workspace_id', 'is_sensitive']);
            $table->index(['workspace_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_memories');
    }
};
