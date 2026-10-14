<?php

namespace App\Http\Middleware;

use App\Shared\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backend module gate. Rejects a request to a module that is not enabled for the
 * current company's business-type configuration. This is the real enforcement —
 * the frontend only hides menu items; availability is decided here.
 *
 * Usage: ->middleware('module:inventory')
 */
class EnsureModuleEnabled
{
    public function __construct(private CurrentCompany $current) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $company = $this->current->model();

        if ($company === null || ! $company->hasModule($module)) {
            abort(403, "The '{$module}' module is not enabled for this company.");
        }

        return $next($request);
    }
}
