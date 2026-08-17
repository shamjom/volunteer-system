<?php

use App\Http\Controllers\Agent\ConfirmationController;
use App\Http\Controllers\Agent\EventToolsController;
use App\Http\Controllers\Agent\FaqToolsController;
use App\Http\Controllers\Agent\ProfileToolsController;
use App\Http\Controllers\Agent\RequestToolsController;
use App\Http\Controllers\Agent\TaskToolsController;
use App\Http\Controllers\Agent\TeamToolsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| طبقة التوفيق — سطح عقد الأدوات
|--------------------------------------------------------------------------
|
| مساحة أسماء منفصلة عن /api الحالية. المسار هو اسم الأداة حرفياً، والطريقة
| POST دائماً، والجسم هو معاملات الأداة كما في tools_schema.json.
|
| لوحة الإدارة تبقى على مساراتها القديمة — لا تُلمس.
|
*/

Route::prefix('agent')
    ->middleware(['auth:sanctum', 'agent.actor', 'agent.audit'])
    ->group(function () {

        // بنية تحتية — ليست أداة
        Route::post('/confirmations', [ConfirmationController::class, 'issue']);

        // ── أدوات القراءة (٩) ─────────────────────────────────────────
        Route::post('/list_upcoming_events',   [EventToolsController::class, 'listUpcoming']);
        Route::post('/find_event',             [EventToolsController::class, 'find']);
        Route::post('/list_teams',             [TeamToolsController::class, 'listTeams']);
        Route::post('/get_my_profile',         [ProfileToolsController::class, 'profile']);
        Route::post('/get_my_volunteer_hours', [ProfileToolsController::class, 'hours']);
        Route::post('/get_my_tasks',           [TaskToolsController::class, 'myTasks']);
        Route::post('/get_my_registrations',   [EventToolsController::class, 'myRegistrations']);
        Route::post('/get_my_requests_status', [RequestToolsController::class, 'myRequests']);
        Route::post('/search_faq',             [FaqToolsController::class, 'search']);

        // ── أدوات الكتابة (٦) — كلها خلف بوابة التأكيد ────────────────
        Route::middleware('agent.confirm')->group(function () {
            Route::post('/register_for_event',  [EventToolsController::class, 'register']);
            Route::post('/withdraw_from_event', [EventToolsController::class, 'withdraw']);
            Route::post('/update_my_profile',   [ProfileToolsController::class, 'updateProfile']);
            Route::post('/request_join_team',   [TeamToolsController::class, 'requestJoin']);
            Route::post('/submit_help_request', [RequestToolsController::class, 'submitHelp']);
            Route::post('/update_task_status',  [TaskToolsController::class, 'updateStatus']);
        });
    });
