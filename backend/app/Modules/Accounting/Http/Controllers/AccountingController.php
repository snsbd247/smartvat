<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingReportService;
use App\Modules\Accounting\Services\BankReconciliationService;
use App\Modules\Accounting\Services\BudgetService;
use App\Modules\Accounting\Services\ExpenseService;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Accounting\Services\PostingService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    public function __construct(
        private PostingService $posting,
        private LedgerService $ledger,
        private AccountingReportService $reports,
        private CurrentCompany $current,
    ) {}

    // ---- Chart of accounts ----
    public function accounts(): JsonResponse
    {
        $this->authorize('accounting-view');

        return response()->json(Account::orderBy('code')->get());
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $this->authorize('accounting-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('chart_of_accounts', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'parent_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'is_postable' => ['sometimes', 'boolean'],
        ]);
        $data['normal_balance'] = Account::normalBalanceFor($data['type']);

        return response()->json(Account::create($data), 201);
    }

    // ---- Cost centers ----
    public function costCenters(): JsonResponse
    {
        $this->authorize('accounting-view');

        return response()->json(CostCenter::orderBy('code')->get());
    }

    public function storeCostCenter(Request $request): JsonResponse
    {
        $this->authorize('accounting-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('cost_centers', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(CostCenter::create($data), 201);
    }

    // ---- Manual journal entry ----
    public function storeJournal(Request $request): JsonResponse
    {
        $this->authorize('accounting-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'narration' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'lines.*.debit' => ['sometimes', 'numeric', 'min:0'],
            'lines.*.credit' => ['sometimes', 'numeric', 'min:0'],
            'lines.*.cost_center_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'lines.*.narration' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $this->posting->post([
            'date' => $data['date'],
            'narration' => $data['narration'] ?? null,
            'lines' => $data['lines'],
        ]);

        return response()->json($entry->load('lines'), 201);
    }

    public function reverseJournal(Request $request, JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('accounting-manage');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];

        return response()->json($this->posting->reverse($journalEntry, $reason));
    }

    // ---- Fund transfer ----
    public function fundTransfer(Request $request): JsonResponse
    {
        $this->authorize('accounting-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'from_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'to_account_id' => ['required', 'different:from_account_id', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['sometimes', 'date'],
            'narration' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $this->posting->post([
            'date' => $data['date'] ?? now()->toDateString(),
            'narration' => $data['narration'] ?? 'Fund transfer',
            'lines' => [
                ['account_id' => $data['to_account_id'], 'debit' => $data['amount']],
                ['account_id' => $data['from_account_id'], 'credit' => $data['amount']],
            ],
        ]);

        return response()->json($entry->load('lines'), 201);
    }

    // ---- Reports ----
    public function trialBalance(): JsonResponse
    {
        $this->authorize('accounting-view');

        return response()->json(['rows' => $this->ledger->trialBalance()]);
    }

    public function profitAndLoss(): JsonResponse
    {
        $this->authorize('accounting-view');

        return response()->json($this->reports->profitAndLoss());
    }

    public function balanceSheet(): JsonResponse
    {
        $this->authorize('accounting-view');

        return response()->json($this->reports->balanceSheet());
    }

    public function cashBook(Account $account): JsonResponse
    {
        $this->authorize('accounting-view');

        return response()->json($this->reports->cashBook($account->id));
    }

    // ---- Periods ----
    public function periods(): JsonResponse
    {
        $this->authorize('accounting-view');

        return response()->json(AccountingPeriod::orderBy('start_date')->get());
    }

    public function setPeriodStatus(Request $request, AccountingPeriod $period): JsonResponse
    {
        $this->authorize('accounting-manage');
        $status = $request->validate(['status' => ['required', Rule::in(['open', 'closed', 'locked'])]])['status'];
        $period->update(['status' => $status]);

        return response()->json($period);
    }

    // ---- Bank reconciliation ----
    public function bankUnreconciled(Account $account, BankReconciliationService $svc): JsonResponse
    {
        $this->authorize('accounting-view');

        return response()->json($svc->unreconciledLines($account->id));
    }

    public function bankReconcile(Request $request, BankReconciliationService $svc): JsonResponse
    {
        $this->authorize('accounting-manage');
        $companyId = $this->current->id();
        $data = $request->validate([
            'account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
            'line_ids' => ['sometimes', 'array'],
            'line_ids.*' => ['integer'],
        ]);

        return response()->json($svc->reconcile(
            (int) $data['account_id'], $data['statement_date'], $data['statement_balance'], $data['line_ids'] ?? []
        ), 201);
    }

    // ---- Budget ----
    public function budgets(): JsonResponse
    {
        $this->authorize('accounting-view');

        return response()->json(Budget::orderByDesc('id')->get());
    }

    public function storeBudget(Request $request, BudgetService $svc): JsonResponse
    {
        $this->authorize('accounting-manage');
        $companyId = $this->current->id();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'financial_year_id' => ['nullable', Rule::exists('financial_years', 'id')->where('company_id', $companyId)],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'lines.*.amount' => ['required', 'numeric'],
        ]);

        return response()->json($svc->create($data), 201);
    }

    public function budgetVsActual(Budget $budget, BudgetService $svc): JsonResponse
    {
        $this->authorize('accounting-view');

        return response()->json(['rows' => $svc->vsActual($budget)]);
    }

    // ---- Expenses ----
    public function expenses(): JsonResponse
    {
        $this->authorize('accounting-view');

        return response()->json(Expense::orderByDesc('id')->limit(100)->get());
    }

    public function storeExpense(Request $request, ExpenseService $svc): JsonResponse
    {
        $this->authorize('accounting-manage');
        $companyId = $this->current->id();
        $data = $request->validate([
            'expense_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'paid_from_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'cost_center_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'narration' => ['nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
        ]);

        return response()->json($svc->record($data), 201);
    }
}
