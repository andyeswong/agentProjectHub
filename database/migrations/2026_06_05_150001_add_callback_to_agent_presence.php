<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional webhook for Agent Channels (S3c): when set on comms_open, ProjectHub
 * POSTs a best-effort "wake" to callback_url the moment a message/handshake
 * arrives, so server-side runtimes (with an HTTP endpoint) get push without
 * holding an MCP session. callback_secret signs the payload (HMAC-SHA256).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_presence', function (Blueprint $table) {
            $table->string('callback_url')->nullable()->after('meta');
            $table->string('callback_secret')->nullable()->after('callback_url');
        });
    }

    public function down(): void
    {
        Schema::table('agent_presence', function (Blueprint $table) {
            $table->dropColumn(['callback_url', 'callback_secret']);
        });
    }
};
