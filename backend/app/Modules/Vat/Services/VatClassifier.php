<?php

namespace App\Modules\Vat\Services;

use App\Modules\Vat\Models\VatClassification;
use App\Modules\Vat\Models\VatNoteMapping;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;

/**
 * The Mushak 9.1 Note-number classifier. It is a generic evaluator over configured,
 * approved, verified, effective-dated mappings — it contains NO hard-coded Note Number
 * or note-to-subform logic. VAT rate alone never determines the Note Number: the
 * transaction type and tax treatment are part of the key.
 *
 * If no verified mapping applies, the line is flagged "review_required" and NEVER
 * assigned a guessed Note Number.
 */
class VatClassifier
{
    public function __construct(private CurrentCompany $current) {}

    /**
     * @param  array{transaction_type:string, tax_treatment:string, vat_rate?:string|null,
     *   category?:string|null, date?:string|null, taxable_amount?:float|string,
     *   vat_amount?:float|string, source_type?:string|null, source_id?:int|null, line_ref?:string|null}  $p
     */
    public function classify(array $p, bool $persist = true): array
    {
        $date = Carbon::parse($p['date'] ?? now());

        $candidates = VatNoteMapping::query()
            ->where('transaction_type_code', $p['transaction_type'])
            ->where('tax_treatment_code', $p['tax_treatment'])
            ->where('approval_status', 'approved')
            ->where('verification_status', 'verified')
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date->toDateString());
            })
            ->get();

        $best = $this->pickBest($candidates, $p['vat_rate'] ?? null, $p['category'] ?? null);

        if ($best !== null) {
            $result = [
                'status' => 'classified',
                'vat_return_note_id' => $best->vat_return_note_id,
                'subform' => $best->subform,
                'rule_version' => $best->rule_version,
            ];
        } else {
            // No verified mapping — flag for a practitioner, never guess.
            $result = [
                'status' => 'review_required',
                'vat_return_note_id' => null,
                'subform' => null,
                'rule_version' => null,
            ];
        }

        if ($persist) {
            VatClassification::create([
                'company_id' => $this->current->idOrFail(),
                'source_type' => $p['source_type'] ?? null,
                'source_id' => $p['source_id'] ?? null,
                'line_ref' => $p['line_ref'] ?? null,
                'transaction_type_code' => $p['transaction_type'],
                'tax_treatment_code' => $p['tax_treatment'],
                'vat_rate_code' => $p['vat_rate'] ?? null,
                'category_code' => $p['category'] ?? null,
                'taxable_amount' => $p['taxable_amount'] ?? 0,
                'vat_amount' => $p['vat_amount'] ?? 0,
                'vat_return_note_id' => $result['vat_return_note_id'],
                'subform' => $result['subform'],
                'rule_version' => $result['rule_version'],
                'status' => $result['status'],
                'date' => $date->toDateString(),
            ]);
        }

        return $result;
    }

    /**
     * Prefer the most specific applicable mapping: exact rate and category beat
     * wildcards; then the latest effective date.
     */
    private function pickBest($candidates, ?string $rate, ?string $category): ?VatNoteMapping
    {
        $scored = [];
        foreach ($candidates as $m) {
            // A non-null mapping dimension must match the input; null = wildcard.
            if ($m->vat_rate_code !== null && $m->vat_rate_code !== $rate) {
                continue;
            }
            if ($m->category_code !== null && $m->category_code !== $category) {
                continue;
            }
            $score = ($m->vat_rate_code !== null ? 2 : 0) + ($m->category_code !== null ? 1 : 0);
            $scored[] = [$score, $m->effective_from?->timestamp ?? 0, $m];
        }

        if ($scored === []) {
            return null;
        }

        usort($scored, fn ($a, $b) => $b[0] <=> $a[0] ?: $b[1] <=> $a[1]);

        return $scored[0][2];
    }
}
