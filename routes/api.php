<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Store;
use App\Http\Controllers\Api\V1\LobConfigController;
use App\Http\Controllers\Api\V1\ProductCollectionController;
use App\Http\Controllers\Api\V1\ProductPdpController;
use App\Http\Controllers\Api\V1\StoreController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::post('/stores/getAllStore', [StoreController::class, 'getAllStore'])
        ->middleware(['auth.api', 'secure.payload', 'log.api']);

    // Public product APIs — no auth required
    Route::get('/lob/{lob}/config', [LobConfigController::class, 'show']);
    Route::get('/lob/{lob}/products', [ProductCollectionController::class, 'lobProducts']);
    Route::get('/lob/{lob}/collections', [ProductCollectionController::class, 'lobCollections']);
    Route::get('/collections/{slug}', [ProductCollectionController::class, 'show']);
    Route::get('/collections/{slug}/products', [ProductCollectionController::class, 'products']);
    Route::get('/products/{handle}/pdp', [ProductPdpController::class, 'show']);
});
