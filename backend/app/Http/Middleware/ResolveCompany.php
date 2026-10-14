<?php

namespace App\Http\Middleware;

use App\Modules\Organization\Models\Company;
use App\Shared\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves and authorizes the active tenant for the request.
 *
 * The company is taken from the `X-Company-Id` header, falling back to the
 * authenticated user's default company. The user MUST have access to it
 * (user_company_access) or the request is rejected with 403 — this is the
 * gate that prevents cross-tenant access. The resolved company is also used as
 * the spatie/permission team id so roles/permissions are scoped per company.
 */
class ResolveCompany
{
    public function __construct(private CurrentCompany $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        $requestedId = $request->header('X-Company-Id');

        if ($requestedId !== null) {
            if (! is_numeric($requestedId) || ! $user->canAccessCompany((int) $requestedId)) {
                abort(403, 'You do not have access to the requested company.');
            }
            $companyId = (int) $requestedId;
        } else {
            $default = $user->companies()
                ->orderByDesc('user_company_access.is_default')
                ->orderBy('companies.id')
                ->first();

            if ($default === null) {
                abort(403, 'No company is assigned to this user.');
            }
            $companyId = $default->id;
        }

        $company = Company::findOrFail($companyId);

        $this->current->set($company);

        // Scope spatie roles/permissions to this company (teams = companies).
        app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);

        $response = $next($request);

        $response->headers->set('X-Company-Id', (string) $companyId);

        return $response;
    }
}
