<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assets (F1): an independent binary store — logos, screenshots, moodboards,
 * brand references. Workspace-scoped (org derived via workspace.org_id), the
 * same as memories/personalities.
 *
 * Assets stand alone: an asset needs NO brand to exist. Brands (F2) merely
 * REFERENCE assets through the brand_assets link table — related, not owned.
 * Deleting a brand never deletes an asset; deleting an asset never breaks a
 * brand (its link just drops).
 *
 * The bytes live on a Laravel Storage disk (storage_disk + storage_key); only
 * metadata + an embedded `description` live here. Search mirrors memory: the
 * description is embedded and (on Postgres) queried via the pgvector HNSW
 * index added in the companion migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();

            // logo | logo-mark | icon | screenshot | reference | moodboard | mockup | other
            $table->string('kind')->default('other')->index();

            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->string('storage_disk')->default('public');
            $table->string('storage_key');                 // path within the disk
            $table->string('checksum')->nullable()->index(); // sha256 (dedupe)

            $table->text('description')->nullable();        // embedded for search
            $table->json('embedding')->nullable();
            $table->string('embedding_model')->nullable();

            $table->json('tags')->nullable();
            $table->uuid('brand_hint')->nullable();         // convenience only — NOT authoritative

            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
