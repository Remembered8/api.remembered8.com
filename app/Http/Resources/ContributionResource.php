<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A tribute left on a dossier. */
class ContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'memorialId' => $this->memorial_id,
            'kind' => $this->kind,
            'authorName' => $this->author_name,
            'relation' => $this->relation,
            'location' => $this->location,
            'body' => $this->body,
            'isApproved' => $this->is_approved,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
