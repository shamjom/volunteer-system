<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);//done

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('admin')->group(function () {//done
        Route::post('/users', [AuthController::class, 'storeUser']);
        Route::patch('/users/{user}/approve', [AuthController::class, 'approve']);
        Route::patch('/users/{user}/reject', [AuthController::class, 'reject']);
    });
});