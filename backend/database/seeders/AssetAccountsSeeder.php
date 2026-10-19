<?php

namespace Database\Seeders;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CompanyAccountMap;
use App\Modules\Organization\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Idempotent backfill for the asset-lifecycle GL roles (revaluation + maintenance)
 * on companies that were seeded before these were added. Safe to re-run.
 */
class AssetAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['3100', 'Revaluation Reserve', 'equity', 'revaluation_reserve'],
            ['3200', 'Opening Balance Equity', 'equity', 'opening_balance_equity'],
            ['5200', 'Asset Maintenance Expense', 'expense', 'asset_maintenance'],
            ['5300', 'Revaluation Loss', 'expense', 'revaluation_loss'],
        ];

        foreach (Company::all() as $company) {
            foreach ($accounts as [$code, $name, $type, $key]) {
                $account = Account::firstOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    [
                        'name' => $name,
                        'type' => $type,
                        'normal_balance' => Account::normalBalanceFor($type),
                        'is_postable' => true,
                        'status' => 'active',
                    ],
                );

                CompanyAccountMap::firstOrCreate(
                    ['company_id' => $company->id, 'key' => $key],
                    ['account_id' => $account->id],
                );
            }
        }
    }
}
