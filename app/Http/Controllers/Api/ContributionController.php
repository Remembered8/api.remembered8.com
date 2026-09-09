<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContributionResource;
use App\Models\Contribution;
use App\Models\Memorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Tributes left on a dossier.
 *
 * No token is required: this is how a stranger pays their respects. That is
 * also why they are rows rather than edits to the dossier, so an open door for
 * kindness is not an open door for rewriting someone's memorial.
 */
class ContributionController extends Controller
{
    /** Approved tributes, newest first. Unapproved letters stay hidden. */
    public function index(string $idOrSlug): JsonResponse
    {
        $memorial = $this->find($idOrSlug);

        $contributions = $memorial->contributions()
            ->approved()
            ->latest()
            ->limit(200)
            ->get();

        return response()->json([
            'contributions' => ContributionResource::collection($contributions),
        ]);
    }

    public function store(Request $request, string $idOrSlug): JsonResponse
    {
        $memorial = $this->find($idOrSlug);

        $data = $request->validate([
            'kind' => ['required', Rule::in(Contribution::KINDS)],
            'authorName' => ['nullable', 'string', 'max:120'],
            'relation' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            // A letter without words is not a letter.
            'body' => ['nullable', 'string', 'max:4000', Rule::requiredIf($request->input('kind') === 'memory')],
        ]);

        $contribution = DB::transaction(function () use ($memorial, $data) {
            $contribution = $memorial->contributions()->create([
                'kind' => $data['kind'],
                'author_name' => $data['authorName'] ?? '',
                'relation' => $data['relation'] ?? '',
                'location' => $data['location'] ?? '',
                'body' => $data['body'] ?? '',
                'is_approved' => Contribution::isAutoApproved($data['kind']),
            ]);

            if ($data['kind'] === 'candle') {
                $memorial->increment('candle_count');
            }

            return $contribution;
        });

        return response()->json(
            ['contribution' => new ContributionResource($contribution)],
            201
        );
    }

    private function find(string $idOrSlug): Memorial
    {
        return Memorial::query()
            ->where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->firstOrFail();
    }
}
