<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountingReportService;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Inventory\Services\InventoryReportService;
use App\Modules\Manufacturing\Services\ProductionService;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Services\CustomerLedgerService;
use App\Modules\Sales\Services\SalesService;

/**
 * End-to-end acceptance scenario: procure raw material → manufacture finished goods →
 * sell and deliver → verify inventory reconciles to GL, the trial balance balances,
 * profit is recognised, and the customer ledger is correct.
 */
it('runs a full procure → produce → sell flow that stays balanced and reconciled', function () {
    $company = makeCompany('ACME');
    setCompanyContext($company);
    mapInventoryAccounts($company);
    $bank = makeAccount($company, '1010', 'asset');

    $rm = makeProduct($company, 'RM');
    $fg = makeProduct($company, 'FG');
    $wh = makeWarehouse($company, 'WH1');
    $supplier = Supplier::create(['company_id' => $company->id, 'code' => 'S1', 'name' => 'Supplier', 'status' => 'active']);
    $customer = Customer::create(['company_id' => $company->id, 'code' => 'C1', 'name' => 'Customer', 'status' => 'active']);

    $purchase = app(PurchaseService::class);
    $production = app(ProductionService::class);
    $sales = app(SalesService::class);

    // 1) Procure raw material: 1000 @ 50 = 50,000.
    $purchase->receiveGoods([
        'supplier_id' => $supplier->id, 'warehouse_id' => $wh->id,
        'lines' => [['product_id' => $rm->id, 'qty' => 1000, 'unit_cost' => 50]],
    ]);

    // 2) Manufacture: BOM 2 RM → 1 FG; order 100 → consume 200 RM (10,000), produce 100 FG.
    $bom = $production->createBom([
        'product_id' => $fg->id, 'output_qty' => 1,
        'lines' => [['component_product_id' => $rm->id, 'qty' => 2]],
    ]);
    $order = $production->createOrder([
        'product_id' => $fg->id, 'bom_id' => $bom->id, 'warehouse_id' => $wh->id, 'planned_qty' => 100,
    ]);
    $production->issueMaterials($order);
    $production->receiveFinished($order->fresh(), 100); // FG unit cost = 10,000 / 100 = 100

    // 3) Sell 50 FG @ 200, deliver (COGS 50*100), receive 4,000.
    $inv = $sales->postInvoice([
        'customer_id' => $customer->id,
        'lines' => [['product_id' => $fg->id, 'qty' => 50, 'unit_price' => 200, 'line_total' => 10000]],
    ]);
    $sales->deliver([
        'customer_id' => $customer->id, 'warehouse_id' => $wh->id, 'sales_invoice_id' => $inv->id,
        'lines' => [['product_id' => $fg->id, 'qty' => 50]],
    ]);
    $sales->recordReceipt([
        'customer_id' => $customer->id, 'amount' => 4000, 'received_in_account_id' => $bank->id,
    ]);

    // --- assertions ---
    // Inventory ↔ GL reconciled: RM 800@50 (40,000) + FG 50@100 (5,000) = 45,000.
    $recon = app(InventoryReportService::class)->reconciliation();
    expect($recon['in_balance'])->toBeTrue();
    expect((float) $recon['inventory_gl_balance'])->toBe(45000.0);

    // Trial balance balances.
    $tb = app(LedgerService::class)->trialBalance();
    $debit = array_sum(array_map(fn ($r) => (float) $r['debit'], $tb));
    $credit = array_sum(array_map(fn ($r) => (float) $r['credit'], $tb));
    expect(abs($debit - $credit))->toBeLessThan(0.0001);

    // Profit recognised: revenue 10,000 − COGS 5,000 = 5,000.
    $pl = app(AccountingReportService::class)->profitAndLoss();
    expect((float) $pl['net_profit'])->toBe(5000.0);

    // Balance sheet balances.
    expect(app(AccountingReportService::class)->balanceSheet()['in_balance'])->toBeTrue();

    // Customer still owes 10,000 − 4,000 = 6,000.
    expect((float) app(CustomerLedgerService::class)->outstanding($customer->id))->toBe(6000.0);

    // WIP fully cleared.
    expect((float) app(LedgerService::class)->accountBalance(Account::where('code', '1350')->first()))->toBe(0.0);
});
