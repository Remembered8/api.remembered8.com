<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tributes left by visitors, kept apart from the dossier document.
 *
 * Anyone may append here without holding the edit token, which is why these are
 * rows rather than edits: a candle counts at once, while a written letter
 * arrives unapproved and a guardian decides whether it joins the public record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('memorial_id');
            $table->string('kind');
            $table->string('author_name')->default('');
            $table->string('relation')->default('');
            $table->string('location')->default('');
            // MySQL refuses a default on a TEXT column (error 1101), so the empty
            // body is defaulted on the model instead. SQLite accepted it, which is
            // why this only surfaced on the first MySQL migration.
            $table->text('body');
            $table->json('payload')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            $table->foreign('memorial_id')
                ->references('id')
                ->on('memorials')
                ->cascadeOnDelete();

            $table->index(['memorial_id', 'created_at']);
            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
