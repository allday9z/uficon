<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Store;
use App\Http\Controllers\Api\V1\StoreController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::post('/stores/getAllStore', [StoreController::class, 'getAllStore'])
        ->middleware(['auth.api', 'secure.payload', 'log.api']);
});
