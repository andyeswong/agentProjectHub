<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brands (F2): the visual identity a project wears — the counterpart to a
 * personality (the self an agent wears). Same shape, different axis:
 *
 *   brand(parent) -> brand(child)      merged on resolve
 *
 * A brand carries `tokens` (colors/fonts/radii/layout), `voice` and `rules`
 * (do/don't). Inheritance via parent_id lets a house brand seed its variants:
 * enteracloud -> purple-ai -> purplemx-assessment. Resolution deep-merges the
 * chain root-first, deepest wins per token key; rules are unioned.
 *
 * Brands REFERENCE assets through brand_assets; they never own them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('slug')->index();          // 'alti', 'purple-ai'
            $table->string('name')->nullable();
            $table->uuid('parent_id')->nullable()->index(); // extends another brand

            $table->json('tokens')->nullable();       // colors/fonts/radii/layout/components
            $table->json('voice')->nullable();        // tone of voice
            $table->json('rules')->nullable();        // list (union across the chain)
            $table->json('meta')->nullable();

            $table->boolean('is_default')->default(false); // workspace fallback brand
            $table->string('status')->default('active');   // draft | active
            $table->unsignedInteger('version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('last_updated_by')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'slug'], 'brands_workspace_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
