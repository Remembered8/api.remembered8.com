<?php

namespace Database\Seeders;

use App\Models\Memorial;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Loads the curated dossiers.
 *
 * The source of truth is database/seeds/memorials.json, exported from the
 * client's own seed data so the registry and the apps cannot drift. Records are
 * marked is_seed, which makes the API refuse to rewrite them.
 */
class MemorialSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeds/memorials.json');

        if (! is_file($path)) {
            throw new RuntimeException(
                "Missing {$path}. Export it with: node scripts/export-seed-dossiers.mjs"
            );
        }

        $documents = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($documents as $document) {
            $memorial = Memorial::firstOrNew(['id' => $document['id']]);
            $memorial->id = $document['id'];
            $memorial->syncFromDocument($document);
            $memorial->is_seed = true;

            // Curated records have no owner, so no token is ever issued.
            $memorial->edit_token_hash = null;
            $memorial->save();
        }

        $this->command?->info('Seeded '.count($documents).' dossiers.');
    }
}
