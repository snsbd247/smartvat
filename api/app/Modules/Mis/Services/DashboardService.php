<?php

namespace App\Modules\Mis\Services;

use App\Modules\Accounting\Services\AccountingReportService;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Services\InventoryReportService;
use App\Modules\MasterData\Models\Product;
use App\Modules\MasterData\Models\ProductCategory;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Purchase\Models\ImportShipment;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceLine;
use App\Shared\Support\Num;
use Illuminate\Support\Carbon;

/**
 * Consolidated executive-dashboard data (one call → all widgets). Read-only; every
 * figure comes from posted data in the active company. Portable aggregation (grouping
 * done in PHP) so it works on both MySQL and SQLite.
 */
class DashboardService
{
    public function __construct(
        private MisService $mis,
        private InventoryReportService $invReports,
        private AccountingReportService $accReports,
    ) {}

    public function data(): array
    {
        return [
            'kpis' => $this->kpis(),
            'sales_purchase_trend' => $this->trend(),
            'production_output' => $this->productionOutput(),
            'inventory_top' => $this->inventoryTop(),
            'sales_by_category' => $this->salesByCategory(),
            'top_customers' => $this->topCustomers(),
            'recent_transactions' => $this->recentTransactions(),
            'pending_approvals' => $this->mis->approvals(),
        ];
    }

    private function kpis(): array
    {
        $exec = $this->mis->executive();

        $localSales = (string) SalesInvoice::where('kind', 'local')->sum('total');
        $exportSales = (string) SalesInvoice::where('kind', 'export')->sum('total');
        $localPurchase = (string) PurchaseInvoice::sum('total');
        $importPurchase = (string) ImportShipment::where('status', 'received')->sum('landed_value_bc');

        return array_merge($exec, [
            'total_sales' => Num::add($localSales, $exportSales),
            'local_sales' => $localSales,
            'export_sales' => $exportSales,
            'total_purchase' => Num::add($localPurchase, $importPurchase),
            'local_purchase' => $localPurchase,
            'import_purchase' => $importPurchase,
            'production_output' => (string) ProductionOrder::sum('produced_qty'),
        ]);
    }

    private function trend(): array
    {
        $year = now()->year;
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = sprintf('%04d-%02d', $year, $m);
            $months[$key] = ['month' => Carbon::create($year, $m, 1)->format('M'), 'sales' => '0', 'purchase' => '0'];
        }

        foreach (SalesInvoice::whereYear('date', $year)->get(['date', 'total']) as $si) {
            $k = $si->date->format('Y-m');
            if (isset($months[$k])) {
                $months[$k]['sales'] = Num::add($months[$k]['sales'], (string) $si->total);
            }
        }
        foreach (PurchaseInvoice::whereYear('date', $year)->get(['date', 'total']) as $pi) {
            $k = $pi->date->format('Y-m');
            if (isset($months[$k])) {
                $months[$k]['purchase'] = Num::add($months[$k]['purchase'], (string) $pi->total);
            }
        }

        return array_values($months);
    }

    private function productionOutput(): array
    {
        $byProduct = [];
        foreach (ProductionOrder::where('produced_qty', '>', 0)->get(['product_id', 'produced_qty']) as $po) {
            $byProduct[$po->product_id] = Num::add($byProduct[$po->product_id] ?? '0', (string) $po->produced_qty);
        }
        $names = Product::whereIn('id', array_keys($byProduct))->pluck('name', 'id');

        $rows = [];
        foreach ($byProduct as $pid => $qty) {
            $rows[] = ['product' => $names[$pid] ?? ('#'.$pid), 'qty' => $qty];
        }
        usort($rows, fn ($a, $b) => (float) $b['qty'] <=> (float) $a['qty']);

        return array_slice($rows, 0, 6);
    }

    private function inventoryTop(): array
    {
        $balances = StockBalance::where('owner_type', 'company')->get();
        $byProduct = [];
        $total = Num::zero();
        foreach ($balances as $b) {
            $byProduct[$b->product_id] = Num::add($byProduct[$b->product_id] ?? '0', (string) $b->value);
            $total = Num::add($total, (string) $b->value);
        }
        arsort($byProduct);
        $names = Product::whereIn('id', array_keys($byProduct))->pluck('name', 'id');

        $rows = [];
        foreach (array_slice($byProduct, 0, 5, true) as $pid => $value) {
            $rows[] = [
                'item' => $names[$pid] ?? ('#'.$pid),
                'value' => $value,
                'pct' => Num::cmp($total, 0) > 0 ? Num::mul(Num::div($value, $total), 100) : '0',
            ];
        }

        return $rows;
    }

    private function salesByCategory(): array
    {
        $lines = SalesInvoiceLine::with('product:id,category_id')->get(['product_id', 'line_total']);
        $byCat = [];
        $total = Num::zero();
        foreach ($lines as $l) {
            $cat = $l->product?->category_id ?? 0;
            $byCat[$cat] = Num::add($byCat[$cat] ?? '0', (string) $l->line_total);
            $total = Num::add($total, (string) $l->line_total);
        }
        arsort($byCat);
        $names = ProductCategory::whereIn('id', array_keys($byCat))->pluck('name', 'id');

        $rows = [];
        foreach ($byCat as $cid => $amount) {
            $rows[] = [
                'category' => $cid ? ($names[$cid] ?? ('#'.$cid)) : 'Uncategorised',
                'amount' => $amount,
                'pct' => Num::cmp($total, 0) > 0 ? Num::mul(Num::div($amount, $total), 100) : '0',
            ];
        }

        return array_slice($rows, 0, 6);
    }

    private function topCustomers(): array
    {
        $byCustomer = [];
        foreach (SalesInvoice::get(['customer_id', 'total', 'kind']) as $si) {
            $byCustomer[$si->customer_id]['amount'] = Num::add($byCustomer[$si->customer_id]['amount'] ?? '0', (string) $si->total);
            $byCustomer[$si->customer_id]['kind'] = $si->kind;
        }
        uasort($byCustomer, fn ($a, $b) => (float) $b['amount'] <=> (float) $a['amount']);
        $names = Customer::whereIn('id', array_keys($byCustomer))->pluck('name', 'id');

        $rows = [];
        foreach (array_slice($byCustomer, 0, 5, true) as $cid => $data) {
            $rows[] = ['name' => $names[$cid] ?? ('#'.$cid), 'type' => $data['kind'], 'amount' => $data['amount']];
        }

        return $rows;
    }

    private function recentTransactions(): array
    {
        $tx = [];
        foreach (SalesInvoice::latest('id')->limit(5)->get() as $r) {
            $tx[] = ['date' => $r->date->toDateString(), 'type' => 'Sales Invoice', 'doc_no' => $r->inv_no, 'amount' => (string) $r->total, 'status' => $r->status, 'at' => $r->created_at];
        }
        foreach (PurchaseInvoice::latest('id')->limit(5)->get() as $r) {
            $tx[] = ['date' => $r->date->toDateString(), 'type' => 'Purchase Invoice', 'doc_no' => $r->pi_no, 'amount' => (string) $r->total, 'status' => $r->status, 'at' => $r->created_at];
        }
        foreach (ProductionOrder::latest('id')->limit(5)->get() as $r) {
            $tx[] = ['date' => $r->date->toDateString(), 'type' => 'Production', 'doc_no' => $r->order_no, 'amount' => '', 'status' => $r->status, 'at' => $r->created_at];
        }

        usort($tx, fn ($a, $b) => ($b['at'] ?? 0) <=> ($a['at'] ?? 0));
        foreach ($tx as &$t) {
            unset($t['at']);
        }

        return array_slice($tx, 0, 8);
    }
}
