<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemorialResource;
use App\Http\Resources\MemorialSummaryResource;
use App\Models\Memorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The memorial registry.
 *
 * Authority model, deliberately narrow because there are no accounts yet:
 *  - Creating a dossier mints an edit token, returned once.
 *  - Rewriting one requires that token.
 *  - Seeded historical figures are curated and refuse rewrites outright.
 * Tributes are handled by ContributionController and need no token at all.
 */
class MemorialController extends Controller
{
    /** Public dossiers only: unlisted and family-only records are not enumerable. */
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 200), 1), 500);

        $memorials = Memorial::query()
            ->public()
            ->orderByDesc('is_verified_historic')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'memorials' => MemorialSummaryResource::collection($memorials),
        ]);
    }

    /** One dossier by id or slug. Reachable with the id even when unlisted. */
    public function show(string $idOrSlug): JsonResponse
    {
        return response()->json([
            'memorial' => new MemorialResource($this->find($idOrSlug)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $document = $this->validatedDocument($request);
        $id = $document['id'];
        $slug = $document['slug'] ?? $id;

        $taken = Memorial::query()
            ->where('id', $id)
            ->orWhere('slug', $slug)
            ->exists();

        if ($taken) {
            return response()->json(
                ['error' => 'A dossier with this id or slug already exists.'],
                409
            );
        }

        $memorial = new Memorial(['id' => $id]);
        $memorial->id = $id;
        $memorial->syncFromDocument($document);
        $token = $memorial->mintEditToken();
        $memorial->save();

        // Shown exactly once. It is not recoverable from the database.
        return response()->json([
            'memorial' => new MemorialResource($memorial),
            'editToken' => $token,
        ], 201);
    }

    public function update(Request $request, string $idOrSlug): JsonResponse
    {
        $memorial = $this->find($idOrSlug);

        if ($memorial->is_seed) {
            return response()->json(
                ['error' => 'Seeded historical dossiers are curated and cannot be rewritten here.'],
                403
            );
        }

        if (! $memorial->editTokenMatches($request->bearerToken())) {
            return response()->json(['error' => 'A valid edit token is required.'], 403);
        }

        $memorial->syncFromDocument($this->validatedDocument($request));
        $memorial->save();

        return response()->json(['memorial' => new MemorialResource($memorial)]);
    }

    private function find(string $idOrSlug): Memorial
    {
        return Memorial::query()
            ->where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->firstOrFail();
    }

    /**
     * The clients post the whole profile. Only the fields the registry relies on
     * are constrained; the rest of the document is theirs to shape.
     */
    private function validatedDocument(Request $request): array
    {
        $payload = $request->input('document', $request->all());

        $validated = validator(
            ['document' => $payload],
            [
                'document' => ['required', 'array'],
                'document.id' => ['required', 'string', 'max:200'],
                'document.slug' => ['nullable', 'string', 'max:200'],
                'document.fullName' => ['required', 'string', 'max:255'],
                'document.privacy' => ['nullable', 'in:public,family_only,private_link'],
            ]
        )->validate();

        return $validated['document'];
    }
}
