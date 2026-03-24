<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->enum('owner_type', ['agent', 'human'])->default('agent');
            $table->string('model')->nullable();
            $table->string('model_provider')->nullable();
            $table->string('client_type')->default('custom');
            $table->string('pilot')->nullable();
            $table->string('pilot_contact')->nullable();
            $table->json('permissions')->nullable();
            $table->unsignedInteger('rate_limit')->default(120);
            $table->string('system_prompt_hash')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
