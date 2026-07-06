<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VolunteerController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);//done
Route::post('/forgot-password',[AuthController::class,'forgotPassword']);//done
Route::post('/reset-password',[AuthController::class,'resetPassword']);//done

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);//done
    Route::post('/logout', [AuthController::class, 'logout']);//done

    Route::middleware('admin')->group(function () {

        Route::post('/users', [AuthController::class, 'storeUser']);//done
        Route::patch('/users/{user}/approve', [AuthController::class, 'approve']);//done
        Route::patch('/users/{user}/reject', [AuthController::class, 'reject']);//done
        Route::get('/volunteers',[VolunteerController::class,'index']);//done 
        Route::put('/volunteers/{user}',[VolunteerController::class,'update']);//done
        Route::delete('/volunteers/{user}',[VolunteerController::class,'destroy']);//done
        Route::get('/volunteers/{user}',[VolunteerController::class,'show']);//done
        Route::patch('/volunteers/{user}/hours',[VolunteerController::class,'updateHours']);//done
        
        });

});