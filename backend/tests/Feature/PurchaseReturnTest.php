<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Services\PurchaseService;

it('returns goods to a supplier: stock out + Dr AP / Cr Inventory', function () {
    $company = makeCompany('ACME');
    setCompanyContext($company);
    mapInventoryAccounts($company);
    $product = makeProduct($company, 'RM1');
    $wh = makeWarehouse($company, 'WH1');
    $supplier = Supplier::create(['company_id' => $company->id, 'code' => 'S1', 'name' => 'Seller', 'status' => 'active']);
    $svc = app(PurchaseService::class);

    $svc->receiveGoods([
        'supplier_id' => $supplier->id, 'warehouse_id' => $wh->id,
        'lines' => [['product_id' => $product->id, 'qty' => 100, 'unit_cost' => 10]],
    ]);

    $svc->returnGoods([
        'supplier_id' => $supplier->id, 'warehouse_id' => $wh->id,
        'lines' => [['product_id' => $product->id, 'qty' => 30]],
    ]);

    expect((float) StockBalance::first()->qty)->toBe(70.0);
    // Inventory reduced by 30 @ avg 10 = 300 -> 700.
    expect((float) app(LedgerService::class)->accountBalance(Account::where('code', '1300')->first()))->toBe(700.0);

    $tb = app(LedgerService::class)->trialBalance();
    $d = array_sum(array_map(fn ($r) => (float) $r['debit'], $tb));
    $c = array_sum(array_map(fn ($r) => (float) $r['credit'], $tb));
    expect(abs($d - $c))->toBeLessThan(0.0001);
});
