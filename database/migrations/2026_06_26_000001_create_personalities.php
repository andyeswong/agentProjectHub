<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity layer: a personality is the SELF a stateless body wears. One self
 * (slug, e.g. "maia") is a TREE of layers that cascade at resolve-time:
 *
 *   core  → runtime[client_type]  → channel[client_type + channel]
 *
 * Same MAIA, different register/affordances per body and per channel. The
 * runtime is stateless — it fetches its resolved identity from here each
 * session instead of carrying local soul files.
 *
 * - level             : core | runtime | channel (depth in the cascade)
 * - match_client_type : which body this layer applies to (runtime/channel)
 * - match_channel     : which channel this layer applies to (channel only)
 * - soul              : core = the full who-I-am; deeper = an ADDENDUM
 * - register/model_pref: scalars — deepest non-null wins on resolve
 * - scopes/tools/rules: lists — UNION across layers (variant adds to core)
 * - meta              : escape hatch for runtime-specific knobs
 *
 * Resolution = deep-merge(core, runtime, channel). Mirrors the memory
 * integration philosophy: deeper layers COMPLEMENT the core, never blanket
 * replace it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personalities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('slug')->index();              // groups the tree ('maia')
            $table->string('name')->nullable();           // display ('MAIA')
            $table->uuid('parent_id')->nullable()->index();
            $table->string('level')->default('core');     // core | runtime | channel
            $table->string('match_client_type')->nullable(); // runtime/channel layers
            $table->string('match_channel')->nullable();      // channel layer

            $table->text('soul')->nullable();             // core: full; deeper: addendum
            $table->string('register')->nullable();       // scalar (deepest wins)
            $table->string('model_pref')->nullable();     // hint only — body's brain swaps
            $table->json('scopes')->nullable();           // list (union)
            $table->json('tools')->nullable();            // list (union)
            $table->json('rules')->nullable();            // list (union)
            $table->json('meta')->nullable();             // escape hatch

            $table->string('status')->default('active');  // draft | active
            $table->unsignedInteger('version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('last_updated_by')->nullable();
            $table->timestamps();

            // One layer per (self, level, client_type, channel) within a workspace.
            $table->unique(
                ['workspace_id', 'slug', 'level', 'match_client_type', 'match_channel'],
                'personalities_layer_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personalities');
    }
};
