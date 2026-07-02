<?php

use App\Http\Middleware\TraceRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'orders',
            'orders/*',
        ]);

        // Opens an OpenTelemetry server span for the whole request so DB queries
        // and the Temporal workflow start share one trace id (see TraceRequest).
        $middleware->appendToGroup('web', TraceRequest::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
