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
        $this->seedAccountMap($company);

        if ($code === 'ACME') {
            $this->seedTransactions($company);
        }

        return $company;
    }

    /** A small set of realistic demo transactions so the dashboard shows live figures. */
    private function seedTransactions(Company $company): void
    {
        $wh = $company->warehouses()->firstOrCreate(['code' => 'WH1'], ['name' => 'Main Warehouse', 'status' => 'active']);

        $fg = \App\Modules\MasterData\Models\Product::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'FG-PIPE'],
            ['name' => 'Plastic Pipe', 'type' => 'finished', 'base_uom' => 'PCS', 'costing_method' => 'weighted_average', 'status' => 'active'],
        );
        $rm = \App\Modules\MasterData\Models\Product::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'RM-RESIN'],
            ['name' => 'Raw Resin', 'type' => 'raw', 'base_uom' => 'KG', 'costing_method' => 'weighted_average', 'status' => 'active'],
        );

        $supplier = \App\Modules\Purchase\Models\Supplier::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'SUP-RESIN'],
            ['name' => 'Bright Chemicals', 'status' => 'active'],
        );
        $customer = \App\Modules\Sales\Models\Customer::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'CUS-ABC'],
            ['name' => 'ABC Traders', 'status' => 'active'],
        );

        $purchase = app(\App\Modules\Purchase\Services\PurchaseService::class);
        $sales = app(\App\Modules\Sales\Services\SalesService::class);

        // Receive stock (FG + RM) with matching purchase invoice.
        $grn = $purchase->receiveGoods([
            'supplier_id' => $supplier->id, 'warehouse_id' => $wh->id,
            'lines' => [
                ['product_id' => $fg->id, 'qty' => 500, 'unit_cost' => 100],
                ['product_id' => $rm->id, 'qty' => 1000, 'unit_cost' => 50],
            ],
        ]);
        $purchase->postInvoice([
            'supplier_id' => $supplier->id, 'type' => 'material', 'goods_receipt_note_id' => $grn->id,
            'lines' => [['product_id' => $fg->id, 'line_total' => 100000]],
        ]);

        // Sell some finished goods + deliver (COGS) + partial receipt.
        $inv = $sales->postInvoice([
            'customer_id' => $customer->id,
            'lines' => [['product_id' => $fg->id, 'qty' => 200, 'unit_price' => 150, 'line_total' => 30000]],
        ]);
        $sales->deliver([
            'customer_id' => $customer->id, 'warehouse_id' => $wh->id, 'sales_invoice_id' => $inv->id,
            'lines' => [['product_id' => $fg->id, 'qty' => 200]],
        ]);
        $sales->recordReceipt([
            'customer_id' => $customer->id, 'amount' => 10000,
            'received_in_account_id' => Account::where('company_id', $company->id)->where('code', '1010')->value('id'),
        ]);

        // A draft purchase order awaiting approval (shows in the Approval Center).
        $purchase->createOrder([
            'supplier_id' => $supplier->id,
            'lines' => [['product_id' => $rm->id, 'qty' => 500, 'unit_price' => 50]],
        ]);
    }

    private function seedAccountMap(Company $company): void
    {
        $map = [
            'inventory' => '1300',
            'wip' => '1350',
            'grn_clearing' => '2050',
            'cogs' => '5000',
            'inventory_adjustment' => '5100',
            'accounts_payable' => '2000',
            'accounts_receivable' => '1200',
            'landed_cost_clearing' => '2060',
            'sales_revenue' => '4000',
            'export_revenue' => '4100',
            'exchange_gain_loss' => '4200',
            'processing_income' => '4300',
            'vds_payable' => '2200',
        ];

        foreach ($map as $key => $code) {
            $account = Account::where('company_id', $company->id)->where('code', $code)->first();
            if ($account !== null) {
                \App\Modules\Accounting\Models\CompanyAccountMap::firstOrCreate(
                    ['company_id' => $company->id, 'key' => $key],
                    ['account_id' => $account->id],
                );
            }
        }
    }

    private function seedChartOfAccounts(Company $company): void
    {
        $accounts = [
            ['1000', 'Cash in Hand', 'asset'],
            ['1010', 'Bank Account', 'asset'],
            ['1200', 'Accounts Receivable', 'asset'],
            ['1300', 'Inventory', 'asset'],
            ['1350', 'Work in Progress', 'asset'],
            ['2000', 'Accounts Payable', 'liability'],
            ['2050', 'GRN Clearing', 'liability'],
            ['2060', 'Landed Cost Clearing', 'liability'],
            ['2100', 'VAT Payable', 'liability'],
            ['2200', 'VDS Payable', 'liability'],
            ['3000', 'Share Capital', 'equity'],
            ['4000', 'Sales Revenue', 'income'],
            ['4100', 'Export Sales', 'income'],
            ['4200', 'Exchange Gain/Loss', 'income'],
            ['4300', 'Processing Income', 'income'],
            ['5000', 'Cost of Goods Sold', 'expense'],
            ['5100', 'Inventory Adjustment', 'expense'],
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
