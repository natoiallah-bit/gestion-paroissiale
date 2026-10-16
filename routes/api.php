<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

// Utilisé par les applications téléphone / ordinateur (voir le dossier application/)
Route::get('/ping', fn () => ['ok' => true, 'application' => 'gestion-paroissiale']);
Route::post('/connexion', [ApiController::class, 'connexion'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/deconnexion', [ApiController::class, 'deconnexion']);
    Route::post('/synchroniser', [ApiController::class, 'synchroniser'])->middleware('throttle:120,1');
});
