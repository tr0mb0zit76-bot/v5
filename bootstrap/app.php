<?php

use App\Http\Middleware\EnsureSettingsVisibilityAccess;
use App\Http\Middleware\EnsureVisibilityAnyAreaAccess;
use App\Http\Middleware\EnsureVisibilityAreaAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ReconnectOnPreparedStatementError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'visibility.area' => EnsureVisibilityAreaAccess::class,
            'visibility.area.any' => EnsureVisibilityAnyAreaAccess::class,
            'visibility.settings' => EnsureSettingsVisibilityAccess::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Добавляем глобальный middleware для обработки ошибки 1615 Prepared statement
        $middleware->appendToGroup('web', ReconnectOnPreparedStatementError::class);
        $middleware->appendToGroup('api', ReconnectOnPreparedStatementError::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
