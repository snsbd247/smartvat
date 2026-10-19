<?php

namespace App\Modules\Mis\Services;

use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\MasterData\Models\Product;
use App\Modules\Sales\Models\SalesInvoiceLine;
use App\Shared\Support\Num;

/**
 * Product-level analytics: profit by product (revenue vs estimated cost) and a
 * stock-movement summary (quantity + value in/out) per product.
 */
class ProductReportService
{
    public function __construct(private StockService $stock) {}

    /** Revenue, estimated cost (qty sold × current average cost) and margin per product. */
    public function profitByProduct(): array
    {
        $names = Product::pluck('name', 'id')->all();
        $lines = SalesInvoiceLine::query()->whereNotNull('product_id')->get(['product_id', 'qty', 'line_total']);

        $agg = [];
        foreach ($lines as $l) {
            $pid = $l->product_id;
            $agg[$pid] ??= ['qty' => '0', 'revenue' => '0'];
            $agg[$pid]['qty'] = Num::add($agg[$pid]['qty'], (string) $l->qty);
            $agg[$pid]['revenue'] = Num::add($agg[$pid]['revenue'], (string) $l->line_total);
        }

        $rows = [];
        foreach ($agg as $pid => $a) {
            // Estimated unit cost = current average cost across the product's company stock.
            $unitCost = $this->avgCost((int) $pid);
            $cost = Num::mul($a['qty'], $unitCost);
            $profit = Num::sub($a['revenue'], $cost);
            $margin = Num::cmp($a['revenue'], 0) > 0 ? Num::mul(Num::div($profit, $a['revenue']), 100) : '0';

            $rows[] = [
                'product' => $names[$pid] ?? ('#'.$pid),
                'qty_sold' => $a['qty'],
                'revenue' => $a['revenue'],
                'est_cost' => $cost,
                'profit' => $profit,
                'margin_pct' => $margin,
            ];
        }

        usort($rows, fn ($x, $y) => Num::cmp($y['profit'], $x['profit']));

        return ['rows' => $rows];
    }

    /** In/out quantity and value per product from the movement ledger. */
    public function stockMovement(): array
    {
        $names = Product::pluck('name', 'id')->all();
        $movements = StockMovement::query()->where('owner_type', 'company')->get(['product_id', 'qty', 'value']);

        $agg = [];
        foreach ($movements as $m) {
            $pid = $m->product_id;
            $agg[$pid] ??= ['in_qty' => '0', 'in_val' => '0', 'out_qty' => '0', 'out_val' => '0'];
            if (Num::cmp((string) $m->qty, 0) >= 0) {
                $agg[$pid]['in_qty'] = Num::add($agg[$pid]['in_qty'], (string) $m->qty);
                $agg[$pid]['in_val'] = Num::add($agg[$pid]['in_val'], (string) $m->value);
            } else {
                $agg[$pid]['out_qty'] = Num::add($agg[$pid]['out_qty'], Num::sub(0, (string) $m->qty));
                $agg[$pid]['out_val'] = Num::add($agg[$pid]['out_val'], Num::sub(0, (string) $m->value));
            }
        }

        $rows = [];
        foreach ($agg as $pid => $a) {
            $rows[] = [
                'product' => $names[$pid] ?? ('#'.$pid),
                'in_qty' => $a['in_qty'],
                'in_value' => $a['in_val'],
                'out_qty' => $a['out_qty'],
                'out_value' => $a['out_val'],
                'net_qty' => Num::sub($a['in_qty'], $a['out_qty']),
            ];
        }

        usort($rows, fn ($x, $y) => strcmp($x['product'], $y['product']));

        return ['rows' => $rows];
    }

    private function avgCost(int $productId): string
    {
        // Average across the product's company balances (value / qty).
        $bal = \App\Modules\Inventory\Models\StockBalance::query()
            ->where('product_id', $productId)->where('owner_type', 'company')
            ->selectRaw('SUM(qty) as q, SUM(value) as v')->first();

        $q = (string) ($bal->q ?? 0);
        $v = (string) ($bal->v ?? 0);

        return Num::cmp($q, 0) > 0 ? Num::div($v, $q) : Num::zero();
    }
}
