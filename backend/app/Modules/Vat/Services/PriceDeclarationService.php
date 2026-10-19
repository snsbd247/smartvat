<?php

namespace App\Modules\Vat\Services;

use App\Modules\Vat\Models\PriceDeclaration;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Price (value) declarations — Mushak মূল্য ঘোষণা. Only an approved declaration
 * whose effective date has arrived is the "current" declared price for a product.
 */
class PriceDeclarationService
{
    public function __construct(private CurrentCompany $current, private NumberGenerator $numbers) {}

    public function create(array $data): PriceDeclaration
    {
        return PriceDeclaration::create([
            'company_id' => $this->current->idOrFail(),
            'decl_no' => $this->numbers->next('price_declaration', 'PD-'),
            'product_id' => $data['product_id'],
            'declared_price' => $data['declared_price'],
            'purchase_value' => $data['purchase_value'] ?? null,
            'effective_from' => Carbon::parse($data['effective_from'] ?? now())->toDateString(),
            'status' => 'draft',
            'note' => $data['note'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    public function approve(PriceDeclaration $declaration): PriceDeclaration
    {
        if ($declaration->status === 'approved') {
            throw new InvalidArgumentException('This declaration is already approved.');
        }
        $declaration->update(['status' => 'approved']);

        return $declaration;
    }

    /** The currently effective approved declaration for a product, if any. */
    public function currentFor(int $productId, ?string $onDate = null): ?PriceDeclaration
    {
        $date = Carbon::parse($onDate ?? now())->toDateString();

        return PriceDeclaration::where('product_id', $productId)
            ->where('status', 'approved')
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
