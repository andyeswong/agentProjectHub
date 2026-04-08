<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE agent_memories MODIFY COLUMN type ENUM('credential','domain','ip','fact','config','note','skill','other') NOT NULL DEFAULT 'fact'");
    }

    public function down(): void
    {
        DB::statement("UPDATE agent_memories SET type = 'other' WHERE type = 'skill'");
        DB::statement("ALTER TABLE agent_memories MODIFY COLUMN type ENUM('credential','domain','ip','fact','config','note','other') NOT NULL DEFAULT 'fact'");
    }
};
