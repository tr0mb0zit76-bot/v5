<?php

require_once __DIR__.'/temp-dir.php';

configure_phpword_temp_dir(dirname(__DIR__));

use App\Http\Middleware\EnsureCanManageSalesScripts;
use App\Http\Middleware\EnsureCompanyPlanningAccess;
use App\Http\Middleware\EnsureSettingsVisibilityAccess;
use App\Http\Middleware\EnsureVisibilityAreaAccess;
use App\Http\Middleware\EnsureVisibilityAnyAreaAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ReconnectOnPreparedStatementError;
use App\Http\Middleware\RejectExternalFromInternalRoutes;
use App\Http\Middleware\VerifyAstralEpdWebhookSignature;
use App\Http\Middleware\VerifyOneCFreshToken;
use App\Support\UserFacingDatabaseMessageResolver;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->is('mobile/*') ? '/mobile/login' : '/login');

        $middleware->alias([
            'visibility.area' => EnsureVisibilityAreaAccess::class,
            'visibility.area.any' => EnsureVisibilityAnyAreaAccess::class,
            'company.planning' => EnsureCompanyPlanningAccess::class,
            'visibility.settings' => EnsureSettingsVisibilityAccess::class,
            'can.manage.sales.scripts' => EnsureCanManageSalesScripts::class,
            'verify.astral.epd.signature' => VerifyAstralEpdWebhookSignature::class,
            'verify.onec.token' => VerifyOneCFreshToken::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Добавляем глобальный middleware для обработки ошибки 1615 Prepared statement
        $middleware->appendToGroup('web', ReconnectOnPreparedStatementError::class);
        $middleware->appendToGroup('web', RejectExternalFromInternalRoutes::class);
        $middleware->appendToGroup('api', ReconnectOnPreparedStatementError::class);
        $middleware->validateCsrfTokens(except: [
            'integrations/astral/epd/webhook',
            'integrations/1c-fresh/etrn-status',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (PostTooLargeException $e, Request $request): ?Response {
            $message = 'Размер отправленных данных слишком большой для текущих настроек PHP (post_max_size / upload_max_filesize). '
                .'Увеличьте их в php.ini для вашего сайта (например upload_max_filesize=128M и post_max_size=128M; post_max_size должен быть не меньше upload_max_filesize). '
                .'В OSPanel: настройки модуля PHP → php.ini. После изменения перезапустите веб-сервер.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('flash', [
                    'type' => 'error',
                    'message' => $message,
                ]);
            }

            return response($message, 413)->header('Content-Type', 'text/plain; charset=UTF-8');
        });

        $exceptions->renderable(function (Throwable $e, Request $request): ?Response {
            if ($request->is('up')) {
                return null;
            }

            $message = app(UserFacingDatabaseMessageResolver::class)->resolve($e);

            if ($message === null) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            $flash = [
                'type' => 'error',
                'message' => $message,
            ];

            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('flash', $flash);
            }

            return redirect()->back()->with('flash', $flash);
        });
    })->create();
