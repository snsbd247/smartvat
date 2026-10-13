<?php

namespace Database\Seeders;

use App\Models\User;
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
        $this->makeCompany('ACME', 'ACME Plastics Ltd', 'acme.admin@example.test', 'plastic_manufacturing');
        // BETA: trading (inventory enabled, no production).
        $this->makeCompany('BETA', 'Beta Trading Co', 'beta.admin@example.test', 'general_trading');
        // GAMMA: professional services (no inventory/production).
        $this->makeCompany('GAMMA', 'Gamma Services Ltd', 'gamma.admin@example.test', 'professional_services');
    }

    private function makeCompany(string $code, string $name, string $email, string $template): void
    {
        $company = Company::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'base_currency' => 'BDT', 'country' => 'BD', 'status' => 'active'],
        );

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
    }
}
