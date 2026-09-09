<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memorial dossiers.
 *
 * The profile is a deeply nested document (timeline, gallery, audio, memories,
 * family tree, capsules, donations) and the clients consume it whole, so it is
 * stored as one JSON document with the fields the registry queries lifted into
 * columns beside it. Carried over unchanged from the D1 schema it replaces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memorials', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('slug')->unique();
            $table->string('full_name');
            $table->string('profession')->default('');
            $table->string('birth_place')->default('');
            $table->string('category')->default('civilian');
            $table->string('privacy')->default('public');
            $table->boolean('is_verified_historic')->default(false);

            // Curated records. The public API refuses to rewrite these, so a
            // visitor cannot edit Einstein.
            $table->boolean('is_seed')->default(false);

            $table->unsignedBigInteger('candle_count')->default(0);
            $table->json('document');

            // Proof of authorship while there are no accounts: only the hash of
            // the token is kept, never the token itself.
            $table->string('edit_token_hash')->nullable();

            $table->timestamps();

            $table->index('category');
            $table->index('privacy');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memorials');
    }
};
