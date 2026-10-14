<?php

namespace App\Shared\Tenancy;

use App\Modules\Organization\Models\Company;

/**
 * Request-scoped holder for the active tenant (company).
 *
 * Bound as a singleton; set by the ResolveCompany middleware after verifying the
 * authenticated user may access the requested company. The CompanyScope and the
 * BelongsToCompany trait read it to filter and stamp tenant data.
 */
class CurrentCompany
{
    private ?Company $company = null;

    public function set(Company $company): void
    {
        $this->company = $company;
    }

    public function clear(): void
    {
        $this->company = null;
    }

    public function isset(): bool
    {
        return $this->company !== null;
    }

    public function model(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function idOrFail(): int
    {
        if ($this->company === null) {
            throw new \RuntimeException('No current company is set for this request.');
        }

        return $this->company->id;
    }
}
