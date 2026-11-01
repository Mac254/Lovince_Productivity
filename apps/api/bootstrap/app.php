<?php

use App\Http\Middleware\LogApiRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Modules\Auth\Http\Middleware\RenewRememberedLogin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    // API routes live in each module's Routes/api.php, loaded by its provider under /api/v1.
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->appendToGroup('api', [LogApiRequests::class, RenewRememberedLogin::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->expectsJson() ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
