<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResolveCompany;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // All API routes always negotiate JSON (clean 401/403 instead of redirects).
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        // Alias for the tenant resolver; applied after auth on company-scoped routes.
        $middleware->alias([
            'company' => ResolveCompany::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
