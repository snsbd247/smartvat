<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Applies a company's business-type / industry configuration and derives its
 * enabled module set. This is what the Company Setup Wizard calls. The derived
 * company_modules rows are the authoritative input to the backend module gate.
 */
class CompanyConfigurator
{
    /**
     * @param  list<string>  $businessTypes  enabled business types (first = primary unless $primary given)
     */
    public function apply(
        Company $company,
        array $businessTypes,
        ?string $primary = null,
        ?string $industryTemplate = null,
    ): Company {
        $valid = config('erp.business_types');
        $businessTypes = array_values(array_unique($businessTypes));

        if ($businessTypes === []) {
            throw new InvalidArgumentException('At least one business type is required.');
        }

        foreach ($businessTypes as $type) {
            if (! in_array($type, $valid, true)) {
                throw new InvalidArgumentException("Unknown business type: {$type}");
            }
        }

        $primary ??= $businessTypes[0];
        if (! in_array($primary, $businessTypes, true)) {
            throw new InvalidArgumentException('Primary business type must be one of the enabled types.');
        }

        $overrides = $this->templateOverrides($industryTemplate);
        $enabledModules = $this->deriveModules($businessTypes, $overrides);

        DB::transaction(function () use ($company, $businessTypes, $primary, $enabledModules) {
            // Business types
            $company->businessTypes()->delete();
            foreach ($businessTypes as $type) {
                $company->businessTypes()->create([
                    'type' => $type,
                    'is_primary' => $type === $primary,
                ]);
            }

            // Modules (upsert enabled flag for every catalog module)
            foreach (array_keys(config('erp.modules')) as $key) {
                $company->modules()->updateOrCreate(
                    ['module_key' => $key],
                    ['enabled' => in_array($key, $enabledModules, true)],
                );
            }
        });

        return $company->refresh();
    }

    /**
     * @return array{enable: list<string>, disable: list<string>}
     */
    private function templateOverrides(?string $template): array
    {
        if ($template === null) {
            return ['enable' => [], 'disable' => []];
        }

        $templates = config('erp.industry_templates');
        if (! isset($templates[$template])) {
            throw new InvalidArgumentException("Unknown industry template: {$template}");
        }

        return [
            'enable' => $templates[$template]['enable'] ?? [],
            'disable' => $templates[$template]['disable'] ?? [],
        ];
    }

    /**
     * @param  list<string>  $businessTypes
     * @param  array{enable: list<string>, disable: list<string>}  $overrides
     * @return list<string>
     */
    private function deriveModules(array $businessTypes, array $overrides): array
    {
        $enabled = [];

        foreach (config('erp.modules') as $key => $applicable) {
            $on = $applicable === ['*']
                || array_intersect($applicable, $businessTypes) !== [];

            if ($on) {
                $enabled[] = $key;
            }
        }

        $enabled = array_merge($enabled, $overrides['enable']);
        $enabled = array_diff($enabled, $overrides['disable']);

        return array_values(array_unique($enabled));
    }

    /** Resolve the industry template for a company (used by the setup wizard). */
    public function applyIndustryTemplate(Company $company, string $template): Company
    {
        $templates = config('erp.industry_templates');
        if (! isset($templates[$template])) {
            throw new InvalidArgumentException("Unknown industry template: {$template}");
        }

        $def = $templates[$template];

        return $this->apply(
            $company,
            $def['business_types'],
            $def['business_types'][0],
            $template,
        );
    }
}
