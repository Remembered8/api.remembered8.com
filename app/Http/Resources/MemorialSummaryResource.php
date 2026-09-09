<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A dossier as the registry listing presents it, without the document. */
class MemorialSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'fullName' => $this->full_name,
            'profession' => $this->profession,
            'birthPlace' => $this->birth_place,
            'category' => $this->category,
            'privacy' => $this->privacy,
            'isVerifiedHistoric' => $this->is_verified_historic,
            'candleCount' => $this->candle_count,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
