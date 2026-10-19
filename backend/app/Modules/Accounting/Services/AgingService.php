<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Models\SupplierPayment;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\CustomerReceipt;
use App\Modules\Sales\Models\SalesInvoice;
use App\Shared\Support\Num;
use Illuminate\Support\Carbon;

/**
 * Receivable / payable aging. Payments are allocated FIFO (oldest invoice first);
 * each invoice's unsettled remainder is bucketed by its age into 0-30 / 31-60 /
 * 61-90 / 90+ day windows. This is the standard aging approximation when receipts
 * are not allocated to specific invoices.
 */
class AgingService
{
    public function receivables(?string $asOf = null): array
    {
        return $this->build(
            $asOf,
            Customer::pluck('name', 'id')->all(),
            SalesInvoice::query()->orderBy('date')->get(['customer_id', 'date', 'total'])
                ->groupBy('customer_id'),
            CustomerReceipt::query()->get(['customer_id', 'amount'])
                ->groupBy('customer_id')->map(fn ($g) => (string) $g->sum('amount'))->all(),
            'customer_id',
        );
    }

    public function payables(?string $asOf = null): array
    {
        return $this->build(
            $asOf,
            Supplier::pluck('name', 'id')->all(),
            PurchaseInvoice::query()->orderBy('date')->get(['supplier_id', 'date', 'total'])
                ->groupBy('supplier_id'),
            SupplierPayment::query()->get(['supplier_id', 'amount'])
                ->groupBy('supplier_id')->map(fn ($g) => (string) $g->sum('amount'))->all(),
            'supplier_id',
        );
    }

    private function build(?string $asOf, array $names, $invoicesByParty, array $paidByParty, string $key): array
    {
        $today = Carbon::parse($asOf ?? now())->startOfDay();
        $rows = [];
        $totals = ['current' => '0', 'd30' => '0', 'd60' => '0', 'd90' => '0', 'older' => '0', 'total' => '0'];

        foreach ($invoicesByParty as $partyId => $invoices) {
            $remainingPaid = $paidByParty[$partyId] ?? '0';
            $bucket = ['current' => '0', 'd30' => '0', 'd60' => '0', 'd90' => '0', 'older' => '0'];
            $partyTotal = '0';

            foreach ($invoices as $inv) {
                $outstanding = (string) $inv->total;
                // Allocate available payment FIFO against this invoice.
                if (Num::cmp($remainingPaid, 0) > 0) {
                    $applied = Num::cmp($remainingPaid, $outstanding) >= 0 ? $outstanding : $remainingPaid;
                    $outstanding = Num::sub($outstanding, $applied);
                    $remainingPaid = Num::sub($remainingPaid, $applied);
                }
                if (Num::cmp($outstanding, 0) <= 0) {
                    continue;
                }

                $age = (int) abs($today->diffInDays(Carbon::parse($inv->date)->startOfDay()));
                $slot = $age <= 30 ? 'current' : ($age <= 60 ? 'd30' : ($age <= 90 ? 'd60' : ($age <= 120 ? 'd90' : 'older')));
                $bucket[$slot] = Num::add($bucket[$slot], $outstanding);
                $partyTotal = Num::add($partyTotal, $outstanding);
            }

            if (Num::cmp($partyTotal, 0) <= 0) {
                continue;
            }

            $rows[] = array_merge(['party' => $names[$partyId] ?? ('#'.$partyId)], $bucket, ['total' => $partyTotal]);
            foreach ($bucket as $slot => $amt) {
                $totals[$slot] = Num::add($totals[$slot], $amt);
            }
            $totals['total'] = Num::add($totals['total'], $partyTotal);
        }

        return ['rows' => $rows, 'totals' => $totals];
    }
}
