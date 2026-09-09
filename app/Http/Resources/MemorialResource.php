<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** A dossier in full: the summary plus the profile document itself. */
class MemorialResource extends MemorialSummaryResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'document' => $this->document,
        ];
    }
}
