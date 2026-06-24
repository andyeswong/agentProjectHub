<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical PILOT identity. Today api_keys.pilot is free text — one human shows
 * up as 7 variants ("Andres Wong" / "andreswong" / "andres" / ...), so nothing
 * links their agents. A pilots row ties those token variants to ONE identity,
 * which is the prerequisite for per-pilot session continuity (resume "what was
 * I doing anywhere", across agents).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('org_id')->nullable()->index();
            $table->string('display_name');
            $table->json('aliases')->nullable();   // the free-text pilot variants this maps
            $table->json('emails')->nullable();
            $table->timestamps();
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->uuid('pilot_id')->nullable()->after('pilot')->index();
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', fn (Blueprint $t) => $t->dropColumn('pilot_id'));
        Schema::dropIfExists('pilots');
    }
};
