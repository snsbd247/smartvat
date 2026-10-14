<?php

namespace App\Modules\Reporting;

use App\Modules\Accounting\Services\AccountingReportService;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Inventory\Services\InventoryReportService;
use App\Modules\Vat\Services\VatReportService;
use App\Shared\Tenancy\CurrentCompany;
use InvalidArgumentException;

/**
 * Maps a report key to a ReportDefinition built from the existing domain services.
 * Adding a report here makes it available in every format (JSON/HTML/PDF/XLSX) with
 * no extra export code.
 */
class ReportRegistry
{
    public function __construct(private CurrentCompany $current) {}

    public function keys(): array
    {
        return ['trial-balance', 'profit-loss', 'balance-sheet', 'stock-valuation', 'vat-note-summary'];
    }

    public function build(string $key, array $filters = []): ReportDefinition
    {
        $meta = [
            'company' => $this->current->model()?->name,
            'generated_at' => now()->toDayDateTimeString(),
        ];

        return match ($key) {
            'trial-balance' => $this->trialBalance($meta),
            'profit-loss' => $this->profitLoss($meta),
            'balance-sheet' => $this->balanceSheet($meta),
            'stock-valuation' => $this->stockValuation($meta),
            'vat-note-summary' => $this->vatNoteSummary($meta, $filters),
            default => throw new InvalidArgumentException("Unknown report: {$key}"),
        };
    }

    private function trialBalance(array $meta): ReportDefinition
    {
        $rows = app(LedgerService::class)->trialBalance();

        return new ReportDefinition(
            title: 'Trial Balance',
            columns: [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'name', 'label' => 'Account'],
                ['key' => 'debit', 'label' => 'Debit', 'align' => 'right'],
                ['key' => 'credit', 'label' => 'Credit', 'align' => 'right'],
            ],
            rows: $rows,
            meta: $meta,
        );
    }

    private function profitLoss(array $meta): ReportDefinition
    {
        $pl = app(AccountingReportService::class)->profitAndLoss();
        $rows = [];
        foreach ($pl['income_lines'] as $l) {
            $rows[] = ['section' => 'Income', 'name' => $l['code'].' '.$l['name'], 'amount' => $l['balance']];
        }
        foreach ($pl['expense_lines'] as $l) {
            $rows[] = ['section' => 'Expense', 'name' => $l['code'].' '.$l['name'], 'amount' => $l['balance']];
        }
        $rows[] = ['section' => '', 'name' => 'NET PROFIT', 'amount' => $pl['net_profit']];

        return new ReportDefinition(
            title: 'Profit & Loss',
            columns: [
                ['key' => 'section', 'label' => 'Section'],
                ['key' => 'name', 'label' => 'Account'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ],
            rows: $rows,
            meta: $meta,
            totals: ['net_profit' => $pl['net_profit']],
        );
    }

    private function balanceSheet(array $meta): ReportDefinition
    {
        $bs = app(AccountingReportService::class)->balanceSheet();
        $rows = [];
        foreach ($bs['asset_lines'] as $l) {
            $rows[] = ['section' => 'Assets', 'name' => $l['code'].' '.$l['name'], 'amount' => $l['balance']];
        }
        foreach ($bs['liability_lines'] as $l) {
            $rows[] = ['section' => 'Liabilities', 'name' => $l['code'].' '.$l['name'], 'amount' => $l['balance']];
        }
        foreach ($bs['equity_lines'] as $l) {
            $rows[] = ['section' => 'Equity', 'name' => $l['code'].' '.$l['name'], 'amount' => $l['balance']];
        }
        $rows[] = ['section' => 'Equity', 'name' => 'Current-period net profit', 'amount' => $bs['net_profit']];

        return new ReportDefinition(
            title: 'Balance Sheet',
            subtitle: $bs['in_balance'] ? 'In balance' : 'OUT OF BALANCE',
            columns: [
                ['key' => 'section', 'label' => 'Section'],
                ['key' => 'name', 'label' => 'Account'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ],
            rows: $rows,
            meta: $meta,
        );
    }

    private function stockValuation(array $meta): ReportDefinition
    {
        $rows = app(InventoryReportService::class)->valuation();

        return new ReportDefinition(
            title: 'Stock Valuation',
            columns: [
                ['key' => 'product_id', 'label' => 'Product'],
                ['key' => 'warehouse_id', 'label' => 'Warehouse'],
                ['key' => 'qty', 'label' => 'Qty', 'align' => 'right'],
                ['key' => 'avg_cost', 'label' => 'Avg Cost', 'align' => 'right'],
                ['key' => 'value', 'label' => 'Value', 'align' => 'right'],
            ],
            rows: $rows,
            meta: $meta,
        );
    }

    private function vatNoteSummary(array $meta, array $filters): ReportDefinition
    {
        $rows = app(VatReportService::class)->noteSummary($filters['from'] ?? null, $filters['to'] ?? null);

        return new ReportDefinition(
            title: 'Mushak 9.1 — Note-wise Summary',
            columns: [
                ['key' => 'note_code', 'label' => 'Note'],
                ['key' => 'note_title', 'label' => 'Title'],
                ['key' => 'taxable_amount', 'label' => 'Taxable', 'align' => 'right'],
                ['key' => 'vat_amount', 'label' => 'VAT', 'align' => 'right'],
                ['key' => 'count', 'label' => 'Lines', 'align' => 'right'],
            ],
            rows: $rows,
            meta: $meta,
        );
    }
}
