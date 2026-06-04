<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('initiator_id')->constrained('api_keys')->cascadeOnDelete();
            $table->foreignUuid('target_id')->constrained('api_keys')->cascadeOnDelete();

            // pending | open | rejected | closed | expired
            $table->string('status')->default('pending');
            $table->text('intent')->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUuid('closed_by')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->string('close_reason')->nullable();

            $table->timestamp('expires_at')->nullable();      // pending handshake TTL
            $table->timestamp('last_activity_at')->nullable(); // idle auto-close clock
            $table->timestamps();

            $table->index(['target_id', 'status']);
            $table->index(['initiator_id', 'status']);
            $table->index(['org_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_links');
    }
};
