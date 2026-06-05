<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * pgvector optimization: store embeddings in a native vector(1024) column with
 * an HNSW index so semantic search runs as an indexed ANN query (embedding_vec
 * <=> query) instead of loading every row and computing cosine in PHP.
 *
 * Postgres-only. On other drivers (sqlite tests, legacy mysql) this is a no-op
 * and MemoryService falls back to the in-PHP cosine path.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('ALTER TABLE agent_memories ADD COLUMN IF NOT EXISTS embedding_vec vector(1024)');

        // Backfill from the existing json embedding column.
        DB::statement("UPDATE agent_memories SET embedding_vec = embedding::text::vector WHERE embedding IS NOT NULL AND embedding_vec IS NULL");

        // Keep embedding_vec in sync with the json embedding automatically, so
        // the application code (EmbeddingService/MemoryService) stays unchanged.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION agent_memories_sync_embedding_vec() RETURNS trigger AS $$
            BEGIN
                IF NEW.embedding IS NOT NULL THEN
                    NEW.embedding_vec = NEW.embedding::text::vector;
                ELSE
                    NEW.embedding_vec = NULL;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS trg_agent_memories_embedding_vec ON agent_memories');
        DB::statement('CREATE TRIGGER trg_agent_memories_embedding_vec BEFORE INSERT OR UPDATE OF embedding ON agent_memories FOR EACH ROW EXECUTE FUNCTION agent_memories_sync_embedding_vec()');

        // HNSW index for cosine ANN search.
        DB::statement('CREATE INDEX IF NOT EXISTS agent_memories_embedding_vec_hnsw ON agent_memories USING hnsw (embedding_vec vector_cosine_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS agent_memories_embedding_vec_hnsw');
        DB::statement('DROP TRIGGER IF EXISTS trg_agent_memories_embedding_vec ON agent_memories');
        DB::statement('DROP FUNCTION IF EXISTS agent_memories_sync_embedding_vec()');
        DB::statement('ALTER TABLE agent_memories DROP COLUMN IF EXISTS embedding_vec');
    }
};
