<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResolveCompany;
use App\Http\Middleware\SecurityHeaders;
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
        // Security headers on every response.
        $middleware->append(SecurityHeaders::class);

        // All API routes always negotiate JSON (clean 401/403 instead of redirects).
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        // Aliases: tenant resolver + module gate, applied after auth on scoped routes.
        $middleware->alias([
            'company' => ResolveCompany::class,
            'module' => EnsureModuleEnabled::class,
        ]);

        // The tenant must be resolved BEFORE route-model binding, otherwise the
        // company global scope is fail-closed when binding runs and scoped models
        // 404. Pin ResolveCompany/EnsureModuleEnabled ahead of SubstituteBindings.
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            ResolveCompany::class,
            EnsureModuleEnabled::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain/business-rule violations surface as 422 (not 500) for API clients.
        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $domain = $e instanceof \App\Modules\Accounting\Exceptions\UnbalancedEntryException
                || $e instanceof \App\Modules\Accounting\Exceptions\PeriodClosedException
                || $e instanceof \App\Modules\Inventory\Exceptions\InsufficientStockException
                || $e instanceof \InvalidArgumentException;

            if ($domain) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return null;
        });
    })->create();
