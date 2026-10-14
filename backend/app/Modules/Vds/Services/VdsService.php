<?php

namespace App\Modules\Vds\Services;

use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Services\AccountResolver;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Vds\Models\VdsCertificate;
use App\Modules\Vds\Models\VdsDeduction;
use App\Modules\Vds\Models\VdsRule;
use App\Modules\Vds\Models\VdsTreasuryDeposit;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * VAT Deduction at Source. Rates are configurable, versioned, effective-dated and
 * must be verified before use — no hard-coded statutory rates.
 *   Deduct  : withhold from the supplier and owe treasury — Dr AP (supplier) / Cr VDS payable.
 *   Deposit : pay treasury — Dr VDS payable / Cr Cash|Bank.
 * Reconciliation compares total deducted vs total deposited on the VDS-payable account.
 */
class VdsService
{
    public function __construct(
        private CurrentCompany $current,
        private PostingService $posting,
        private AccountResolver $accounts,
        private NumberGenerator $numbers,
    ) {}

    /** Verified+approved rate effective on the date, or null (→ requires review). */
    public function rateFor(string $serviceCategory, string|\DateTimeInterface|null $date = null): ?VdsRule
    {
        $date = Carbon::parse($date ?? now());

        return VdsRule::where('service_category', $serviceCategory)
            ->where('approval_status', 'approved')
            ->where('verification_status', 'verified')
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date->toDateString()))
            ->orderByDesc('effective_from')
            ->first();
    }

    public function deduct(array $data): VdsDeduction
    {
        return DB::transaction(function () use ($data) {
            $date = Carbon::parse($data['date'] ?? now());
            $rule = $this->rateFor($data['service_category'], $date);

            if ($rule === null) {
                throw new RuntimeException(
                    "No verified VDS rule for '{$data['service_category']}'. A qualified practitioner must configure and verify it."
                );
            }

            $base = (string) $data['base_amount'];
            $vds = Num::div(Num::mul($base, (string) $rule->rate), 100);

            $deduction = VdsDeduction::create([
                'company_id' => $this->current->idOrFail(),
                'deduction_no' => $this->numbers->next('vds_deduction', 'VDS-'),
                'supplier_id' => $data['supplier_id'],
                'service_category' => $data['service_category'],
                'base_amount' => $base,
                'rate' => $rule->rate,
                'vds_amount' => $vds,
                'date' => $date->toDateString(),
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'status' => 'deducted',
                'created_by' => auth()->id(),
            ]);

            if (! Num::isZero($vds)) {
                $this->posting->post([
                    'date' => $deduction->date,
                    'narration' => "VDS deduction {$deduction->deduction_no}",
                    'source_type' => 'vds_deduction',
                    'source_id' => $deduction->id,
                    'purpose' => 'primary',
                    'lines' => [
                        [
                            'account_id' => $this->accounts->id('accounts_payable'),
                            'debit' => $vds,
                            'party_type' => 'supplier',
                            'party_id' => $deduction->supplier_id,
                        ],
                        ['account_id' => $this->accounts->id('vds_payable'), 'credit' => $vds],
                    ],
                ]);
            }

            return $deduction;
        });
    }

    public function issueCertificate(VdsDeduction $deduction): VdsCertificate
    {
        return VdsCertificate::firstOrCreate(
            ['company_id' => $deduction->company_id, 'vds_deduction_id' => $deduction->id],
            ['certificate_no' => $this->numbers->next('vds_certificate', 'VDSC-'), 'date' => now()->toDateString()],
        );
    }

    public function depositToTreasury(array $data): VdsTreasuryDeposit
    {
        return DB::transaction(function () use ($data) {
            $amount = (string) $data['amount'];

            $deposit = VdsTreasuryDeposit::create([
                'company_id' => $this->current->idOrFail(),
                'challan_no' => $this->numbers->next('vds_deposit', 'TR-'),
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'amount' => $amount,
                'paid_from_account_id' => $data['paid_from_account_id'],
                'reference' => $data['reference'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->posting->post([
                'date' => $deposit->date,
                'narration' => "VDS treasury deposit {$deposit->challan_no}",
                'source_type' => 'vds_treasury_deposit',
                'source_id' => $deposit->id,
                'purpose' => 'primary',
                'lines' => [
                    ['account_id' => $this->accounts->id('vds_payable'), 'debit' => $amount],
                    ['account_id' => $data['paid_from_account_id'], 'credit' => $amount],
                ],
            ]);

            return $deposit;
        });
    }

    /** Deducted vs deposited reconciliation on the VDS-payable account. */
    public function reconciliation(): array
    {
        $row = JournalLine::query()
            ->where('account_id', $this->accounts->id('vds_payable'))
            ->whereHas('entry', fn ($q) => $q->whereIn('status', ['posted', 'reversed']))
            ->selectRaw('COALESCE(SUM(credit),0) deducted, COALESCE(SUM(debit),0) deposited')
            ->first();

        $deducted = (string) ($row->deducted ?? 0);
        $deposited = (string) ($row->deposited ?? 0);

        return [
            'deducted' => $deducted,
            'deposited' => $deposited,
            'payable' => Num::sub($deducted, $deposited),
        ];
    }
}
