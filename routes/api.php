<?php

use App\Http\Controllers\Api\ContributionController;
use App\Http\Controllers\Api\MemorialController;
use Illuminate\Support\Facades\Route;

/*
 * The public registry.
 *
 * Reads and tributes are open. Rewriting a dossier is gated on the edit token
 * minted when it was created, checked inside MemorialController rather than by
 * middleware, because the token belongs to one record and not to a session.
 */

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'Remembered Memory Archive',
    'runtime' => 'laravel',
    'registryBound' => true,
    'time' => now()->toIso8601String(),
]));

Route::get('/memorials', [MemorialController::class, 'index']);
Route::post('/memorials', [MemorialController::class, 'store']);
Route::get('/memorials/{idOrSlug}', [MemorialController::class, 'show']);
Route::put('/memorials/{idOrSlug}', [MemorialController::class, 'update']);

Route::get('/memorials/{idOrSlug}/contributions', [ContributionController::class, 'index']);
Route::post('/memorials/{idOrSlug}/contributions', [ContributionController::class, 'store']);
