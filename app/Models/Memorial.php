<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A memorial dossier.
 *
 * `document` holds the whole profile as the clients understand it. The columns
 * beside it exist so the registry can be listed, filtered and searched without
 * unpacking every row.
 */
class Memorial extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'slug',
        'full_name',
        'profession',
        'birth_place',
        'category',
        'privacy',
        'is_verified_historic',
        'is_seed',
        'candle_count',
        'document',
        'edit_token_hash',
    ];

    protected $hidden = [
        'edit_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'is_verified_historic' => 'boolean',
            'is_seed' => 'boolean',
            'candle_count' => 'integer',
        ];
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /** Only dossiers the registry may list publicly. */
    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }

    /**
     * Mints an edit token, storing only its hash.
     *
     * Returned once to the creator; there is no way to recover it afterwards,
     * which is the honest consequence of having no accounts yet.
     */
    public function mintEditToken(): string
    {
        $token = Str::random(64);
        $this->edit_token_hash = Hash::make($token);

        return $token;
    }

    /** True when the presented token may rewrite this dossier. */
    public function editTokenMatches(?string $token): bool
    {
        if ($token === null || $this->edit_token_hash === null) {
            return false;
        }

        return Hash::check($token, $this->edit_token_hash);
    }

    /**
     * Keeps the queryable columns in step with the document they summarise.
     *
     * The clients send the whole profile; without this the columns would drift
     * from it the first time somebody renamed a dossier.
     */
    public function syncFromDocument(array $document): void
    {
        $this->fill([
            'slug' => $document['slug'] ?? $document['id'] ?? $this->id,
            'full_name' => $document['fullName'] ?? '',
            'profession' => $document['profession'] ?? '',
            'birth_place' => $document['birthPlace'] ?? '',
            'category' => $document['category'] ?? 'civilian',
            'privacy' => $document['privacy'] ?? 'public',
            'is_verified_historic' => (bool) ($document['isVerifiedHistoric'] ?? false),
            'candle_count' => (int) ($document['candleCount'] ?? 0),
            'document' => $document,
        ]);
    }
}
