<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FinancialYear;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Services\CompanyConfigurator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Development demo data only — two separate companies (tenants), each with its own
 * admin user, to exercise tenant isolation. Uses obviously fake data and a dev
 * password; never run against production and never contains real personal data.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ACME: plastic manufacturing (inventory/production/job-work enabled).
        $acme = $this->makeCompany('ACME', 'ACME Plastics Ltd', 'acme.admin@example.test', 'plastic_manufacturing');
        // BETA: trading (inventory enabled, no production).
        $beta = $this->makeCompany('BETA', 'Beta Trading Co', 'beta.admin@example.test', 'general_trading');
        // GAMMA: professional services (no inventory/production).
        $gamma = $this->makeCompany('GAMMA', 'Gamma Services Ltd', 'gamma.admin@example.test', 'professional_services');

        // Platform super-admin that can access every demo company.
        $this->makeSuperAdmin('admin@irlbd.com', 'admin@123', [$acme, $beta, $gamma]);
    }

    /**
     * @param  array<int, Company>  $companies
     */
    private function makeSuperAdmin(string $email, string $password, array $companies): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'System Administrator',
                'username' => 'admin',
                'password' => Hash::make($password),
                'status' => 'active',
            ],
        );

        foreach ($companies as $i => $company) {
            $company->users()->syncWithoutDetaching([
                $user->id => ['is_default' => $i === 0],
            ]);
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
            $user->assignRole('super-admin');
        }
    }

    private function makeCompany(string $code, string $name, string $email, string $template): Company
    {
        $company = Company::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'base_currency' => 'BDT', 'country' => 'BD', 'status' => 'active'],
        );

        // Scope tenant-aware models (accounts, periods) to this company while seeding.
        app(\App\Shared\Tenancy\CurrentCompany::class)->set($company);

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name.' Admin',
                'username' => strtolower($code).'_admin',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $company->users()->syncWithoutDetaching([
            $admin->id => ['is_default' => true],
        ]);

        // Assign the admin role within this company's team context.
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $admin->assignRole('admin');

        // Apply the industry template → business types + enabled modules.
        app(CompanyConfigurator::class)->applyIndustryTemplate($company, $template);

        $company->factories()->firstOrCreate(
            ['code' => 'F1'],
            ['name' => $name.' Factory 1', 'status' => 'active'],
        );

        $this->seedChartOfAccounts($company);
        $this->seedFinancialYear($company);

        return $company;
    }

    private function seedChartOfAccounts(Company $company): void
    {
        $accounts = [
            ['1000', 'Cash in Hand', 'asset'],
            ['1010', 'Bank Account', 'asset'],
            ['1200', 'Accounts Receivable', 'asset'],
            ['1300', 'Inventory', 'asset'],
            ['2000', 'Accounts Payable', 'liability'],
            ['2100', 'VAT Payable', 'liability'],
            ['3000', 'Share Capital', 'equity'],
            ['4000', 'Sales Revenue', 'income'],
            ['5000', 'Cost of Goods Sold', 'expense'],
        ];

        foreach ($accounts as [$code, $cname, $type]) {
            Account::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $cname,
                    'type' => $type,
                    'normal_balance' => Account::normalBalanceFor($type),
                    'is_postable' => true,
                    'status' => 'active',
                ],
            );
        }
    }

    private function seedFinancialYear(Company $company): void
    {
        $fy = FinancialYear::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'FY2026'],
            ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open'],
        );

        AccountingPeriod::firstOrCreate(
            ['company_id' => $company->id, 'financial_year_id' => $fy->id, 'name' => '2026'],
            ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open'],
        );
    }
}
