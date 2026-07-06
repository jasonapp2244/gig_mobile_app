<?php

use App\Http\Middleware\SetLocale;
use App\Http\Middleware\UserActivity;
use Illuminate\Foundation\Application;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\SetAdminTimezone;
use App\Http\Middleware\UpdateLastActivity;
use App\Http\Middleware\Authenticate;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //  $middleware->web(\App\Http\Middleware\Localization::class);
        $middleware->append(UpdateLastActivity::class);
        $middleware->alias([
             'setAdminTimezone'=>SetAdminTimezone::class, // 👈 yahan add karo
            'auth' => Authenticate::class,
            'admin' => AdminMiddleware::class,
            'activity' => UserActivity::class,
            'lastActivity' => UpdateLastActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
