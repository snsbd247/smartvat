<?php

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Services\InventoryReportService;
use App\Modules\JobWork\Services\JobWorkService;
use App\Modules\Sales\Models\Customer;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company); // accounts_receivable + processing_income
    $this->material = makeProduct($this->company, 'CUST-RM');
    $this->wh = makeWarehouse($this->company, 'WH1');
    $this->customer = Customer::create([
        'company_id' => $this->company->id, 'code' => 'CUS1', 'name' => 'Brand Owner Ltd', 'status' => 'active',
    ]);
    $this->jw = app(JobWorkService::class);
    $this->order = $this->jw->createOrder([
        'customer_id' => $this->customer->id, 'warehouse_id' => $this->wh->id, 'planned_qty' => 100,
    ]);
});

it('receives customer material into custody without touching company inventory or GL', function () {
    $this->jw->receiveCustomerMaterial($this->order, [['product_id' => $this->material->id, 'qty' => 100]]);

    // Customer-owned balance exists, valued at zero to the company.
    $bal = StockBalance::where('owner_type', 'customer')->first();
    expect((float) $bal->qty)->toBe(100.0);
    expect((int) $bal->owner_id)->toBe($this->customer->id);

    // No company-owned stock, and no journal entry was created for custody.
    expect(StockBalance::where('owner_type', 'company')->count())->toBe(0);
    expect(JournalEntry::count())->toBe(0);

    // Company inventory↔GL reconciliation unaffected (both zero).
    expect(app(InventoryReportService::class)->reconciliation()['in_balance'])->toBeTrue();
});

it('reconciles customer material: received − consumed = balance', function () {
    $this->jw->receiveCustomerMaterial($this->order, [['product_id' => $this->material->id, 'qty' => 100]]);
    $this->jw->consumeCustomerMaterial($this->order, [['product_id' => $this->material->id, 'qty' => 60]]);

    $recon = $this->jw->materialReconciliation($this->customer->id, $this->material->id);
    expect((float) $recon['received'])->toBe(100.0);
    expect((float) $recon['consumed_or_delivered'])->toBe(60.0);
    expect((float) $recon['balance'])->toBe(40.0);
});

it('charges the processing (conversion) service, not a sale of goods', function () {
    $invoice = $this->jw->processingInvoice([
        'customer_id' => $this->customer->id,
        'job_work_order_id' => $this->order->id,
        'qty' => 100, 'rate' => 5,
    ]);

    expect((float) $invoice->amount)->toBe(500.0);

    $ledger = app(LedgerService::class);
    // AR up, processing income recognised — no COGS / no goods sale.
    expect((float) $ledger->accountBalance(App\Modules\Accounting\Models\Account::where('code', '1200')->first()))->toBe(500.0);
    expect((float) $ledger->accountBalance(App\Modules\Accounting\Models\Account::where('code', '4300')->first()))->toBe(500.0);
});
