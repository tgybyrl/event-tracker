<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Any failed call to the Go API — refused connection, timeout, or a
        // 4xx/5xx answer — shows one page naming the missing service instead
        // of Laravel's generic 500. The exception is still logged as usual.
        $exceptions->render(
            fn (HttpClientException $e) => response()->view('errors.events-api', status: 503),
        );
    })->create();
