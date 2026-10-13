<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Exceptions\PeriodClosedException;
use App\Modules\Accounting\Exceptions\UnbalancedEntryException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The single server-side entry point for double-entry posting. Guarantees:
 *  - every posted entry balances (sum of debits == sum of credits)
 *  - posting is idempotent per (source_type, source_id, purpose)
 *  - posting into a closed/locked period is rejected
 *  - posted entries are never mutated; corrections go through reverse()
 */
class PostingService
{
    public function __construct(
        private CurrentCompany $current,
        private NumberGenerator $numbers,
    ) {}

    /**
     * @param  array{
     *   date: string|\DateTimeInterface,
     *   narration?: string|null,
     *   source_type?: string|null,
     *   source_id?: int|null,
     *   purpose?: string,
     *   currency?: string,
     *   lines: array<int, array{account_id:int, debit?:float|string, credit?:float|string, cost_center_id?:int|null, narration?:string|null}>
     * }  $data
     */
    public function post(array $data): JournalEntry
    {
        $companyId = $this->current->idOrFail();
        $purpose = $data['purpose'] ?? 'primary';
        $sourceType = $data['source_type'] ?? null;
        $sourceId = $data['source_id'] ?? null;

        // Idempotency: a sourced transaction maps to at most one live entry.
        if ($sourceType !== null && $sourceId !== null) {
            $existing = JournalEntry::where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('purpose', $purpose)
                ->where('status', '!=', 'reversed')
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $lines = $this->validateLines($data['lines'] ?? []);
        $date = Carbon::parse($data['date']);
        $period = $this->resolvePeriod($date);

        return DB::transaction(function () use ($companyId, $data, $lines, $date, $period, $sourceType, $sourceId, $purpose) {
            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'entry_no' => $this->numbers->next('journal_entry', 'JV-'),
                'date' => $date->toDateString(),
                'period_id' => $period?->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'purpose' => $purpose,
                'narration' => $data['narration'] ?? null,
                'currency' => $data['currency'] ?? ($this->current->model()->base_currency ?? 'BDT'),
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'company_id' => $companyId,
                    'account_id' => $line['account_id'],
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'currency' => $data['currency'] ?? 'BDT',
                    'fx_rate' => $line['fx_rate'] ?? 1,
                    'narration' => $line['narration'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * Reverse a posted entry with a mirror entry. The original is never modified.
     */
    public function reverse(JournalEntry $entry, string $reason, string|\DateTimeInterface|null $date = null): JournalEntry
    {
        if ($entry->status !== 'posted') {
            throw new InvalidArgumentException('Only posted entries can be reversed.');
        }

        $companyId = $this->current->idOrFail();
        $date = Carbon::parse($date ?? now());
        $period = $this->resolvePeriod($date);

        return DB::transaction(function () use ($entry, $reason, $companyId, $date, $period) {
            $reversal = JournalEntry::create([
                'company_id' => $companyId,
                'entry_no' => $this->numbers->next('journal_entry', 'JV-'),
                'date' => $date->toDateString(),
                'period_id' => $period?->id,
                'purpose' => 'reversal',
                'narration' => "Reversal of {$entry->entry_no}: {$reason}",
                'currency' => $entry->currency,
                'status' => 'posted',
                'reversal_of_id' => $entry->id,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'created_by' => auth()->id(),
            ]);

            foreach ($entry->lines as $line) {
                // Swap debit and credit to reverse the effect.
                $reversal->lines()->create([
                    'company_id' => $companyId,
                    'account_id' => $line->account_id,
                    'cost_center_id' => $line->cost_center_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'currency' => $line->currency,
                    'fx_rate' => $line->fx_rate,
                    'narration' => 'Reversal',
                ]);
            }

            $entry->update(['status' => 'reversed']);

            return $reversal->load('lines');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawLines
     * @return array<int, array{account_id:int, debit:string, credit:string, cost_center_id?:int|null, narration?:string|null, fx_rate?:mixed}>
     */
    private function validateLines(array $rawLines): array
    {
        if (count($rawLines) < 2) {
            throw new InvalidArgumentException('A journal entry needs at least two lines.');
        }

        $accountIds = array_values(array_unique(array_map(fn ($l) => (int) $l['account_id'], $rawLines)));
        $accounts = Account::whereIn('id', $accountIds)->get()->keyBy('id');

        if ($accounts->count() !== count($accountIds)) {
            throw new InvalidArgumentException('One or more accounts do not belong to this company.');
        }

        $totalDebit = Num::zero();
        $totalCredit = Num::zero();
        $lines = [];

        foreach ($rawLines as $raw) {
            $account = $accounts[(int) $raw['account_id']] ?? null;
            if ($account === null || ! $account->is_postable) {
                throw new InvalidArgumentException('Account is not postable: '.($raw['account_id'] ?? '?'));
            }

            $debit = $raw['debit'] ?? 0;
            $credit = $raw['credit'] ?? 0;

            if (Num::isNegative($debit) || Num::isNegative($credit)) {
                throw new InvalidArgumentException('Debit and credit must be non-negative.');
            }

            $hasDebit = ! Num::isZero($debit);
            $hasCredit = ! Num::isZero($credit);

            if ($hasDebit === $hasCredit) {
                throw new InvalidArgumentException('Each line must have exactly one of debit or credit.');
            }

            $totalDebit = Num::add($totalDebit, $debit);
            $totalCredit = Num::add($totalCredit, $credit);

            $lines[] = [
                'account_id' => (int) $raw['account_id'],
                'debit' => Num::add($debit, 0),
                'credit' => Num::add($credit, 0),
                'cost_center_id' => $raw['cost_center_id'] ?? null,
                'narration' => $raw['narration'] ?? null,
                'fx_rate' => $raw['fx_rate'] ?? 1,
            ];
        }

        if (Num::isZero($totalDebit)) {
            throw new InvalidArgumentException('A journal entry cannot be for zero.');
        }

        if (! Num::eq($totalDebit, $totalCredit)) {
            throw new UnbalancedEntryException($totalDebit, $totalCredit);
        }

        return $lines;
    }

    private function resolvePeriod(Carbon $date): ?AccountingPeriod
    {
        $period = AccountingPeriod::where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();

        if ($period !== null && ! $period->isOpen()) {
            throw new PeriodClosedException($date->toDateString());
        }

        return $period;
    }
}
