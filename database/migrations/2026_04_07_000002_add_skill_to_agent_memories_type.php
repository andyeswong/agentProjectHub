<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ENUM MODIFY is MySQL-specific. Other drivers (e.g. sqlite for tests)
        // store the type as a string with no DB-level enum to widen.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE agent_memories MODIFY COLUMN type ENUM('credential','domain','ip','fact','config','note','skill','other') NOT NULL DEFAULT 'fact'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            DB::statement("UPDATE agent_memories SET type = 'other' WHERE type = 'skill'");
            return;
        }
        DB::statement("UPDATE agent_memories SET type = 'other' WHERE type = 'skill'");
        DB::statement("ALTER TABLE agent_memories MODIFY COLUMN type ENUM('credential','domain','ip','fact','config','note','other') NOT NULL DEFAULT 'fact'");
    }
};
