<?php

namespace App\Modules\Vat\Services;

use App\Modules\Vat\Models\VatClassification;
use App\Modules\Vat\Models\VatReturnNote;
use App\Shared\Support\Num;

class VatReportService
{
    /**
     * Mushak 9.1 note-wise summary: classified amounts aggregated by Note Number.
     */
    public function noteSummary(?string $from = null, ?string $to = null): array
    {
        $rows = VatClassification::query()
            ->where('status', 'classified')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->get()
            ->groupBy('vat_return_note_id');

        $notes = VatReturnNote::whereIn('id', $rows->keys()->filter())->get()->keyBy('id');

        $summary = [];
        foreach ($rows as $noteId => $group) {
            $taxable = Num::zero();
            $vat = Num::zero();
            foreach ($group as $c) {
                $taxable = Num::add($taxable, (string) $c->taxable_amount);
                $vat = Num::add($vat, (string) $c->vat_amount);
            }
            $summary[] = [
                'note_id' => $noteId,
                'note_code' => $notes[$noteId]->code ?? null,
                'note_title' => $notes[$noteId]->title ?? null,
                'taxable_amount' => $taxable,
                'vat_amount' => $vat,
                'count' => $group->count(),
            ];
        }

        return $summary;
    }

    /** Transactions that could not be mapped to a verified Note Number. */
    public function unmapped(): array
    {
        return VatClassification::where('status', 'review_required')
            ->orderByDesc('id')
            ->get(['id', 'source_type', 'source_id', 'line_ref', 'transaction_type_code',
                'tax_treatment_code', 'vat_rate_code', 'taxable_amount', 'vat_amount', 'date'])
            ->all();
    }

    /**
     * Purchase (input) or sales (output) register from classifications.
     */
    public function register(string $direction): array
    {
        $types = $direction === 'output'
            ? ['local_sale', 'export_sale']
            : ['local_purchase', 'import_purchase', 'service_purchase'];

        return VatClassification::whereIn('transaction_type_code', $types)
            ->orderBy('date')
            ->get()
            ->all();
    }
}
