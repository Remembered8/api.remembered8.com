<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tribute left on a dossier: a candle, a memory letter, a flower, a tree, a
 * prayer. Appended by anyone, which is why it is a row and not an edit.
 */
class Contribution extends Model
{
    use HasFactory;
    use HasUuids;

    /** The kinds a visitor may leave. */
    public const KINDS = ['candle', 'memory', 'flower', 'tree', 'prayer'];

    protected $fillable = [
        'memorial_id',
        'kind',
        'author_name',
        'relation',
        'location',
        'body',
        'payload',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_approved' => 'boolean',
        ];
    }

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Whether this kind is published the moment it arrives.
     *
     * A candle is a gesture and counts at once; written words are held for a
     * guardian to read first.
     */
    public static function isAutoApproved(string $kind): bool
    {
        return $kind !== 'memory';
    }
}
