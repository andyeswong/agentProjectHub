<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_presence', function (Blueprint $table) {
            // One row per agent (api_key). agent_id is the primary key.
            $table->foreignUuid('agent_id')->primary()->constrained('api_keys')->cascadeOnDelete();
            $table->string('status')->default('unavailable'); // unavailable | available
            $table->timestamp('available_since')->nullable();
            $table->timestamp('last_heartbeat')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_presence');
    }
};
