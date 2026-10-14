<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Resources\CompanyResource;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    /**
     * Companies the authenticated user may access (for the company switcher).
     * Not tenant-scoped: it is the list of tenants, keyed to the user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return CompanyResource::collection($request->user()->companies()->orderBy('name')->get());
    }

    /**
     * The currently active company for this request.
     */
    public function current(CurrentCompany $current): CompanyResource
    {
        return new CompanyResource($current->model());
    }
}
