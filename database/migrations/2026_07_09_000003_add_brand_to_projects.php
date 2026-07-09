<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Which brand does this project follow?" — the pointer that lets an agent
 * working in a repo call brand_resolve(project_id) and get the right tokens.
 * Nullable: a project without a brand falls back to the workspace default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->uuid('brand_id')->nullable()->index()->after('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('brand_id');
        });
    }
};
