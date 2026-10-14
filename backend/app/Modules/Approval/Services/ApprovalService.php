<?php

namespace App\Modules\Approval\Services;

use App\Modules\Approval\Models\Approval;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Generic document approval. Any model can raise a pending approval; approving/rejecting
 * updates the approval and keeps the underlying document's status in sync.
 */
class ApprovalService
{
    public function __construct(private CurrentCompany $current) {}

    public function request(Model $document, ?string $documentNo = null, string|float|null $amount = null): Approval
    {
        return Approval::create([
            'company_id' => $this->current->idOrFail(),
            'approvable_type' => $document::class,
            'approvable_id' => $document->getKey(),
            'document_no' => $documentNo,
            'amount' => $amount,
            'status' => 'pending',
            'requested_by' => auth()->id(),
        ]);
    }

    public function approve(Approval $approval): Approval
    {
        return $this->act($approval, 'approved');
    }

    public function reject(Approval $approval, ?string $note = null): Approval
    {
        return $this->act($approval, 'rejected', $note);
    }

    private function act(Approval $approval, string $status, ?string $note = null): Approval
    {
        if ($approval->status !== 'pending') {
            throw new InvalidArgumentException('This approval has already been actioned.');
        }

        $approval->update([
            'status' => $status,
            'note' => $note,
            'acted_by' => auth()->id(),
            'acted_at' => now(),
        ]);

        // Keep the underlying document in sync when it carries a status column.
        $document = $approval->approvable;
        if ($document !== null && $document->getConnection()->getSchemaBuilder()->hasColumn($document->getTable(), 'status')) {
            $target = $status === 'approved' ? 'approved' : 'cancelled';
            $fill = ['status' => $target];
            if ($document->isFillable('approved_by') || in_array('approved_by', $document->getFillable(), true)) {
                $fill['approved_by'] = auth()->id();
                $fill['approved_at'] = now();
            }
            $document->forceFill($fill)->save();
        }

        return $approval;
    }
}
