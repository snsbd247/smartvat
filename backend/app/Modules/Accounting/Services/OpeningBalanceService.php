<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Inventory\Services\StockService;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Opening balances for a company going live. Stock and GL balances are booked
 * against the Opening Balance Equity account so the ledger stays balanced; that
 * equity account is later cleared to capital/retained earnings by the accountant.
 */
class OpeningBalanceService
{
    public function __construct(
        private CurrentCompany $current,
        private StockService $stock,
        private PostingService $posting,
        private AccountResolver $accounts,
    ) {}

    /**
     * Opening stock: brings quantity + value into inventory and books the value to
     * Opening Balance Equity (Dr Inventory / Cr Opening Balance Equity).
     *
     * @param  array<int, array{product_id:int, qty:float|string, unit_cost:float|string, batch_id?:int}>  $lines
     */
    public function openingStock(int $warehouseId, array $lines, ?string $date = null): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Opening stock needs at least one line.');
        }

        return DB::transaction(function () use ($warehouseId, $lines, $date) {
            $stockLines = [];
            foreach ($lines as $l) {
                $stockLines[] = [
                    'product_id' => $l['product_id'],
                    'warehouse_id' => $warehouseId,
                    'qty' => (string) $l['qty'],
                    'unit_cost' => (string) $l['unit_cost'],
                    'batch_id' => $l['batch_id'] ?? 0,
                    'source_type' => 'opening_balance',
                    'reference' => 'OPENING',
                ];
            }
            $movements = $this->stock->apply($stockLines);

            $total = Num::zero();
            foreach ($movements as $m) {
                $total = Num::add($total, (string) $m->value);
            }

            if (! Num::isZero($total)) {
                $this->posting->post([
                    'date' => Carbon::parse($date ?? now())->toDateString(),
                    'narration' => 'Opening stock',
                    'source_type' => 'opening_balance',
                    'source_id' => $warehouseId,
                    'purpose' => 'opening_stock_'.uniqid(),
                    'lines' => [
                        ['account_id' => $this->accounts->id('inventory'), 'debit' => $total],
                        ['account_id' => $this->accounts->id('opening_balance_equity'), 'credit' => $total],
                    ],
                ]);
            }

            return ['lines' => count($lines), 'value' => $total];
        });
    }

    /**
     * Opening GL balances: each line debits/credits an account with the contra to
     * Opening Balance Equity, so every entry is balanced on its own.
     *
     * @param  array<int, array{account_id:int, debit?:float|string, credit?:float|string}>  $lines
     */
    public function openingAccounts(array $lines, ?string $date = null): int
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Provide at least one opening balance line.');
        }

        return DB::transaction(function () use ($lines, $date) {
            $equity = $this->accounts->id('opening_balance_equity');
            $count = 0;

            foreach ($lines as $l) {
                $accountId = (int) $l['account_id'];
                if ($accountId === $equity) {
                    throw new InvalidArgumentException('Do not set an opening balance on the Opening Balance Equity account itself.');
                }
                $debit = (string) ($l['debit'] ?? 0);
                $credit = (string) ($l['credit'] ?? 0);
                if (Num::isZero($debit) && Num::isZero($credit)) {
                    continue;
                }
                if (! Num::isZero($debit) && ! Num::isZero($credit)) {
                    throw new InvalidArgumentException('A line is either a debit or a credit, not both.');
                }

                Account::findOrFail($accountId); // company-scoped existence check

                $amount = Num::isZero($debit) ? $credit : $debit;
                $accountDebit = ! Num::isZero($debit);

                $this->posting->post([
                    'date' => Carbon::parse($date ?? now())->toDateString(),
                    'narration' => 'Opening balance',
                    'source_type' => 'opening_balance',
                    'source_id' => $accountId,
                    'purpose' => 'opening_account_'.$accountId,
                    'lines' => $accountDebit
                        ? [
                            ['account_id' => $accountId, 'debit' => $amount],
                            ['account_id' => $equity, 'credit' => $amount],
                        ]
                        : [
                            ['account_id' => $accountId, 'credit' => $amount],
                            ['account_id' => $equity, 'debit' => $amount],
                        ],
                ]);
                $count++;
            }

            return $count;
        });
    }
}
