<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lazy reference index: keep context CLEAN by carrying POINTERS, not payloads.
 * Instead of inlining the design system (or any heavy artifact) into every
 * wear, a personality layer holds typed pointers — "X lives at id Y, fetch it
 * when Z" — and the body pulls the content only on the matching trigger.
 *
 *   refs: [{ kind:'memory'|'skill'|'tool'|'scope', ref:<id|key|name>,
 *            when:<trigger hint>, load:'eager'|'lazy', note:?string }]
 *
 * eager = inject at wear (tiny always-on). lazy = only the pointer travels;
 * content fetched on demand (and spreading-activation pulls its neighbours).
 * Unioned across the cascade like scopes/tools.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personalities', function (Blueprint $table) {
            $table->json('refs')->nullable()->after('rules');
        });
    }

    public function down(): void
    {
        Schema::table('personalities', function (Blueprint $table) {
            $table->dropColumn('refs');
        });
    }
};
