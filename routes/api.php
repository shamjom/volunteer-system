<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VolunteerController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskAssignmentController;

  Route::post('/login', [AuthController::class, 'login']);//done
  Route::post('/forgot-password',[AuthController::class,'forgotPassword']);//done
  Route::post('/reset-password',[AuthController::class,'resetPassword']);//done

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);//done
    Route::post('/logout', [AuthController::class, 'logout']);//done
    
    Route::get('/events', [EventController::class, 'index']);//done
    Route::get('/events/{event}', [EventController::class, 'show']);//done


Route::middleware('admin')->group(function () {
        
    Route::post('/users', [AuthController::class, 'storeUser']);//done
    Route::patch('/users/{user}/approve', [AuthController::class, 'approve']);//done
    Route::patch('/users/{user}/reject', [AuthController::class, 'reject']);//done

    Route::get('/volunteers',[VolunteerController::class,'index']);//done 
    Route::put('/volunteers/{user}',[VolunteerController::class,'update']);//done
    Route::delete('/volunteers/{user}',[VolunteerController::class,'destroy']);//done
    Route::get('/volunteers/{user}',[VolunteerController::class,'show']);//done
    Route::patch('/volunteers/{user}/hours',[VolunteerController::class,'updateHours']);//done

    Route::post('/events', [EventController::class, 'store']);//done
    Route::put('/events/{event}', [EventController::class, 'update']);//done
    Route::delete('/events/{event}', [EventController::class, 'destroy']);//done

    //الإحصائيات
    Route::get('/tasks/statistics',[TaskController::class,'statistics']);

    Route::post('/tasks', [TaskController::class, 'store']);//done
    Route::put('/tasks/{task}',[TaskController::class,'update']);//done
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);//done
    Route::get('/tasks',[TaskController::class,'index']);//done
    Route::get('/tasks/{task}',[TaskController::class,'show']);//done
    Route::get('/tasks/search',[TaskController::class,'search']);//done


    Route::patch('/tasks/{task}/status',[TaskController::class,'updateStatus']);//done
    Route::get('/events/{event}/tasks',[TaskController::class,'tasksByEvent']);//done
    Route::post('/tasks/{task}/assign',[TaskAssignmentController::class,'assign']);//done
    Route::delete('/tasks/{task}/users/{user}',[TaskAssignmentController::class,'unassign']);//done
    Route::get('/tasks/{task}/volunteers',[TaskAssignmentController::class,'volunteers']);//done
     
    });


});