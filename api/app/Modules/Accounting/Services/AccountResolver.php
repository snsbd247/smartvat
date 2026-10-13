<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\CompanyAccountMap;
use RuntimeException;

/**
 * Resolves a posting role (e.g. 'inventory', 'cogs') to the GL account id
 * configured for the current company. Keeps account numbers out of code.
 */
class AccountResolver
{
    /** @var array<string,int> */
    private array $cache = [];

    public function id(string $key): int
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $map = CompanyAccountMap::where('key', $key)->first();

        if ($map === null) {
            throw new RuntimeException("No GL account is mapped for '{$key}'. Configure the company account map.");
        }

        return $this->cache[$key] = $map->account_id;
    }
}
