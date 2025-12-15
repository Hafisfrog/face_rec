<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FaceRecognitionController;

/*
|--------------------------------------------------------------------------
| Public API (ต้องมี API KEY)
|--------------------------------------------------------------------------
*/
Route::middleware('api.key')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/face/recognize', [FaceRecognitionController::class, 'recognize']);

});

/*
|--------------------------------------------------------------------------
| Protected API (API KEY + LOGIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['api.key', 'auth:sanctum'])->post(
    '/auth/logout',
    [AuthController::class, 'logout']
);
