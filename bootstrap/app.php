<?php

use App\Http\Middleware\ClerkAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'clerk.auth' => ClerkAuth::class,
        ]);
        $middleware->group('api', [
            \App\Http\Middleware\Cors::class,
            // Other middleware, e.g., 'throttle:api', 'auth:api', etc.
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
