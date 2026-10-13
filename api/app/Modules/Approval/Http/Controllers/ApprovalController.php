<?php

namespace App\Modules\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Approval\Models\Approval;
use App\Modules\Approval\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('approval-view');
        $status = $request->query('status', 'pending');

        $rows = Approval::query()
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->with('requestedByUser:id,name', 'actedByUser:id,name')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Approval $a) => [
                'id' => $a->id,
                'type' => $a->typeLabel(),
                'document_no' => $a->document_no,
                'amount' => $a->amount,
                'status' => $a->status,
                'note' => $a->note,
                'requested_by' => $a->requestedByUser?->name,
                'acted_by' => $a->actedByUser?->name,
                'acted_at' => $a->acted_at,
            ]);

        return response()->json($rows);
    }

    public function approve(Approval $approval, ApprovalService $svc): JsonResponse
    {
        $this->authorize('approval-manage');

        return response()->json($svc->approve($approval));
    }

    public function reject(Request $request, Approval $approval, ApprovalService $svc): JsonResponse
    {
        $this->authorize('approval-manage');
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        return response()->json($svc->reject($approval, $note));
    }
}
