<?php

use App\Modules\Accounting\Exceptions\PeriodClosedException;
use App\Modules\Accounting\Exceptions\UnbalancedEntryException;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FinancialYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Accounting\Services\PostingService;

function post(array $data): JournalEntry
{
    return app(PostingService::class)->post($data);
}

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->cash = makeAccount($this->company, '1000', 'asset');
    $this->sales = makeAccount($this->company, '4000', 'income');
});

it('posts a balanced entry', function () {
    $entry = post([
        'date' => '2026-10-13',
        'narration' => 'Cash sale',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 100],
            ['account_id' => $this->sales->id, 'credit' => 100],
        ],
    ]);

    expect($entry->status)->toBe('posted');
    expect($entry->entry_no)->toStartWith('JV-');
    expect($entry->lines)->toHaveCount(2);
});

it('rejects an unbalanced entry', function () {
    expect(fn () => post([
        'date' => '2026-10-13',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 100],
            ['account_id' => $this->sales->id, 'credit' => 90],
        ],
    ]))->toThrow(UnbalancedEntryException::class);

    expect(JournalEntry::count())->toBe(0); // nothing persisted
});

it('rejects an entry with a single line', function () {
    expect(fn () => post([
        'date' => '2026-10-13',
        'lines' => [['account_id' => $this->cash->id, 'debit' => 100]],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a line with both debit and credit', function () {
    expect(fn () => post([
        'date' => '2026-10-13',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 100, 'credit' => 100],
            ['account_id' => $this->sales->id, 'credit' => 100],
        ],
    ]))->toThrow(InvalidArgumentException::class);
});

it('is idempotent for the same source transaction', function () {
    $payload = [
        'date' => '2026-10-13',
        'source_type' => 'sales_invoice',
        'source_id' => 42,
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 100],
            ['account_id' => $this->sales->id, 'credit' => 100],
        ],
    ];

    $first = post($payload);
    $second = post($payload);

    expect($second->id)->toBe($first->id);
    expect(JournalEntry::count())->toBe(1);
});

it('rejects posting into a closed period', function () {
    $fy = FinancialYear::create([
        'company_id' => $this->company->id, 'name' => 'FY2026',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open',
    ]);
    AccountingPeriod::create([
        'company_id' => $this->company->id, 'financial_year_id' => $fy->id,
        'name' => 'Oct-2026', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
        'status' => 'locked',
    ]);

    expect(fn () => post([
        'date' => '2026-10-13',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 100],
            ['account_id' => $this->sales->id, 'credit' => 100],
        ],
    ]))->toThrow(PeriodClosedException::class);
});

it('reverses a posted entry leaving a zero net balance', function () {
    $entry = post([
        'date' => '2026-10-13',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 100],
            ['account_id' => $this->sales->id, 'credit' => 100],
        ],
    ]);

    $reversal = app(PostingService::class)->reverse($entry, 'mistake');

    expect($reversal->reversal_of_id)->toBe($entry->id);
    expect($entry->fresh()->status)->toBe('reversed');

    $ledger = app(LedgerService::class);
    expect($ledger->accountBalance($this->cash->fresh()))->toEqual('0.0000');
    expect($ledger->accountBalance($this->sales->fresh()))->toEqual('0.0000');
});

it('produces a balanced trial balance', function () {
    post([
        'date' => '2026-10-13',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 250],
            ['account_id' => $this->sales->id, 'credit' => 250],
        ],
    ]);

    $tb = app(LedgerService::class)->trialBalance();
    $debit = array_sum(array_map(fn ($r) => (float) $r['debit'], $tb));
    $credit = array_sum(array_map(fn ($r) => (float) $r['credit'], $tb));

    expect($debit)->toEqual($credit);
    expect($debit)->toEqual(250.0);
});
