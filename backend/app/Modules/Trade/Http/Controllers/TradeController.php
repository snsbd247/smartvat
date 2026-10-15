<?php

namespace App\Modules\Trade\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Trade\Models\ExportDocument;
use App\Modules\Trade\Models\Shipment;
use App\Modules\Trade\Models\TradeFinance;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TradeController extends Controller
{
    public function __construct(private CurrentCompany $current, private NumberGenerator $numbers) {}

    // ---- LC / TT ----
    public function financeIndex(): JsonResponse
    {
        $this->authorize('trade-view');

        return response()->json(TradeFinance::orderByDesc('id')->limit(300)->get());
    }

    public function financeStore(Request $request): JsonResponse
    {
        $this->authorize('trade-manage');
        $data = $request->validate([
            'kind' => ['required', Rule::in(['lc', 'tt'])],
            'party' => ['nullable', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'ref_number' => ['nullable', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'min:0'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['company_id'] = $this->current->idOrFail();
        $data['tf_no'] = $this->numbers->next('trade_finance', strtoupper($data['kind']).'-');
        $data['status'] = 'open';
        $data['created_by'] = auth()->id();

        return response()->json(TradeFinance::create($data), 201);
    }

    public function financeStatus(Request $request, TradeFinance $tradeFinance): JsonResponse
    {
        $this->authorize('trade-manage');
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'partial', 'settled', 'expired', 'cancelled'])]]);
        $tradeFinance->update($data);

        return response()->json($tradeFinance);
    }

    // ---- Shipments ----
    public function shipmentIndex(): JsonResponse
    {
        $this->authorize('trade-view');

        return response()->json(Shipment::orderByDesc('id')->limit(300)->get());
    }

    public function shipmentStore(Request $request): JsonResponse
    {
        $this->authorize('trade-manage');
        $data = $request->validate([
            'direction' => ['required', Rule::in(['import', 'export'])],
            'transport_ref' => ['nullable', 'string', 'max:255'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'origin' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'etd' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['company_id'] = $this->current->idOrFail();
        $data['shipment_no'] = $this->numbers->next('shipment', 'SHP-');
        $data['status'] = 'booked';
        $data['created_by'] = auth()->id();

        return response()->json(Shipment::create($data), 201);
    }

    public function shipmentStatus(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('trade-manage');
        $data = $request->validate(['status' => ['required', Rule::in(['booked', 'in_transit', 'arrived', 'cleared', 'delivered'])]]);
        $shipment->update($data);

        return response()->json($shipment);
    }

    // ---- Export documents ----
    public function exportDocIndex(): JsonResponse
    {
        $this->authorize('trade-view');

        return response()->json(ExportDocument::orderByDesc('id')->limit(300)->get());
    }

    public function exportDocStore(Request $request): JsonResponse
    {
        $this->authorize('trade-manage');
        $data = $request->validate([
            'doc_type' => ['required', Rule::in(['exp', 'coo', 'commercial_invoice', 'packing_list'])],
            'party' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['company_id'] = $this->current->idOrFail();
        $data['doc_no'] = $this->numbers->next('export_document', strtoupper($data['doc_type']).'-');
        $data['status'] = 'draft';
        $data['created_by'] = auth()->id();

        return response()->json(ExportDocument::create($data), 201);
    }

    public function exportDocStatus(Request $request, ExportDocument $exportDocument): JsonResponse
    {
        $this->authorize('trade-manage');
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'issued', 'submitted'])]]);
        $exportDocument->update($data);

        return response()->json($exportDocument);
    }
}
