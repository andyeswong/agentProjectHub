<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Full allowed set for agent_memories.type after this migration.
    private const TYPES = "'credential','domain','ip','fact','config','note','skill','other'";

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE agent_memories MODIFY COLUMN type ENUM(" . self::TYPES . ") NOT NULL DEFAULT 'fact'");
            return;
        }

        if ($driver === 'pgsql') {
            // Laravel's ->enum() is a CHECK constraint on Postgres; widen it to include 'skill'.
            DB::statement('ALTER TABLE agent_memories DROP CONSTRAINT IF EXISTS agent_memories_type_check');
            DB::statement('ALTER TABLE agent_memories ADD CONSTRAINT agent_memories_type_check CHECK (type::text = ANY (ARRAY[' . self::TYPES . ']::text[]))');
            return;
        }
        // sqlite / others: type is a plain string check; no-op.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        DB::statement("UPDATE agent_memories SET type = 'other' WHERE type = 'skill'");

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE agent_memories MODIFY COLUMN type ENUM('credential','domain','ip','fact','config','note','other') NOT NULL DEFAULT 'fact'");
            return;
        }
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE agent_memories DROP CONSTRAINT IF EXISTS agent_memories_type_check');
            DB::statement("ALTER TABLE agent_memories ADD CONSTRAINT agent_memories_type_check CHECK (type::text = ANY (ARRAY['credential','domain','ip','fact','config','note','other']::text[]))");
        }
    }
};
