<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\UpdateCompanyConfigRequest;
use App\Modules\Organization\Services\CompanyConfigurator;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;

class CompanyConfigController extends Controller
{
    public function __construct(private CurrentCompany $current) {}

    /**
     * Enabled module keys for the active company — the frontend sidebar manifest.
     */
    public function modules(): JsonResponse
    {
        return response()->json([
            'modules' => $this->current->model()->enabledModuleKeys(),
        ]);
    }

    /**
     * Full configuration: business types + per-module enabled state.
     */
    public function show(): JsonResponse
    {
        $company = $this->current->model();

        return response()->json([
            'company_id' => $company->id,
            'primary_business_type' => $company->primaryBusinessType(),
            'business_types' => $company->businessTypes()->pluck('type'),
            'modules' => $company->modules()
                ->orderBy('module_key')
                ->get(['module_key', 'enabled']),
        ]);
    }

    /**
     * Reconfigure business types / industry template and re-derive modules.
     */
    public function update(UpdateCompanyConfigRequest $request, CompanyConfigurator $configurator): JsonResponse
    {
        $this->authorize('configure-company');

        $data = $request->validated();

        $company = $configurator->apply(
            $this->current->model(),
            $data['business_types'],
            $data['primary_business_type'] ?? null,
            $data['industry_template'] ?? null,
        );

        return response()->json([
            'company_id' => $company->id,
            'primary_business_type' => $company->primaryBusinessType(),
            'business_types' => $company->businessTypes()->pluck('type'),
            'modules' => $company->enabledModuleKeys(),
        ]);
    }
}
