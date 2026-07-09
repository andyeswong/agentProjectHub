<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * pgvector optimization for assets — same pattern as agent_memories: a native
 * vector(1024) column kept in sync from the json `embedding` by a trigger, plus
 * an HNSW cosine index so asset semantic search is an indexed ANN query.
 *
 * Postgres-only. On sqlite/other drivers this is a no-op and AssetService falls
 * back to the in-PHP cosine path.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('ALTER TABLE assets ADD COLUMN IF NOT EXISTS embedding_vec vector(1024)');

        DB::statement("UPDATE assets SET embedding_vec = embedding::text::vector WHERE embedding IS NOT NULL AND embedding_vec IS NULL");

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assets_sync_embedding_vec() RETURNS trigger AS $$
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
        DB::statement('DROP TRIGGER IF EXISTS trg_assets_embedding_vec ON assets');
        DB::statement('CREATE TRIGGER trg_assets_embedding_vec BEFORE INSERT OR UPDATE OF embedding ON assets FOR EACH ROW EXECUTE FUNCTION assets_sync_embedding_vec()');

        DB::statement('CREATE INDEX IF NOT EXISTS assets_embedding_vec_hnsw ON assets USING hnsw (embedding_vec vector_cosine_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS assets_embedding_vec_hnsw');
        DB::statement('DROP TRIGGER IF EXISTS trg_assets_embedding_vec ON assets');
        DB::statement('DROP FUNCTION IF EXISTS assets_sync_embedding_vec()');
        DB::statement('ALTER TABLE assets DROP COLUMN IF EXISTS embedding_vec');
    }
};
