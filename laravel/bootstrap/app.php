<?php

use App\Support\AgentResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // سطح عقد الأدوات — منفصل عن مسارات لوحة الإدارة
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/agent.php'));
        },
    )

    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'admin'         => \App\Http\Middleware\EnsureAdmin::class,
            'agent.actor'   => \App\Http\Middleware\EnsureAgentActor::class,
            'agent.confirm' => \App\Http\Middleware\RequireAgentConfirmation::class,
            'agent.audit'   => \App\Http\Middleware\LogAgentCall::class,
        ]);

    })

    ->withExceptions(function (Exceptions $exceptions): void {

        // كل خطأ على مسارات الأدوات يخرج بالغلاف المجمَّد وبأحد الرموز السبعة
        $exceptions->render(function (Throwable $e, Request $request) {

            if (! $request->is('api/agent/*')) {
                return null;
            }

            // رسائل التحقق الافتراضية في Laravel إنجليزية، و message_ar يُعرض
            // للمستخدم كما هو — فتُستبدل بالرسالة العربية الموحّدة. الحالات
            // ذات المعنى العملي تردّ رسالتها العربية من المتحكم نفسه.
            if ($e instanceof ValidationException) {
                return AgentResponse::fail(AgentResponse::VALIDATION_ERROR);
            }

            if ($e instanceof AuthenticationException) {
                return AgentResponse::fail(
                    AgentResponse::PERMISSION_DENIED,
                    'الجلسة غير صالحة، يرجى تسجيل الدخول من جديد.'
                );
            }

            if ($e instanceof NotFoundHttpException) {
                return AgentResponse::fail(AgentResponse::NOT_FOUND);
            }

            if ($e instanceof QueryException) {
                return AgentResponse::fail(AgentResponse::UPSTREAM_DOWN);
            }

            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 403) {
                return AgentResponse::fail(AgentResponse::PERMISSION_DENIED);
            }

            return AgentResponse::fail(AgentResponse::UPSTREAM_DOWN);
        });

    })

    ->create();
