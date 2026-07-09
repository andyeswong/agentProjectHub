<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The link that keeps F1 and F2 RELATED BUT NOT DEPENDENT.
 *
 * An asset exists on its own (upload a logo, no brand needed). A brand merely
 * points at assets, each in a `role` that tells the Brand Board where to paint
 * it. Cascades run on the LINK only:
 *
 *   delete a brand -> its links go, the assets survive
 *   delete an asset -> its links go, the brand survives (that slot is empty)
 *
 * One asset can serve many brands (a shared wordmark) without duplicating the
 * bytes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No surrogate id: this is a pivot. The (brand_id, asset_id) pair is the
        // key, so belongsToMany can attach/detach without minting a UUID.
        Schema::create('brand_assets', function (Blueprint $table) {
            $table->foreignUuid('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();

            // logo | logo-mark | icon | hero-ref | moodboard | mockup | palette-ref
            $table->string('role')->default('moodboard');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // One link per (brand, asset): the role is a property of that link.
            // Re-attaching the same asset updates its role rather than adding a row.
            $table->unique(['brand_id', 'asset_id'], 'brand_assets_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_assets');
    }
};
