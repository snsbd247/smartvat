<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\StoreWarehouseRequest;
use App\Modules\Organization\Http\Resources\WarehouseResource;
use App\Modules\Organization\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Warehouse::class);

        $warehouses = Warehouse::query()
            ->when($request->filled('factory_id'), fn ($q) => $q->where('factory_id', $request->integer('factory_id')))
            ->orderBy('code')
            ->paginate($request->integer('per_page', 25));

        return WarehouseResource::collection($warehouses);
    }

    public function store(StoreWarehouseRequest $request): WarehouseResource
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = Warehouse::create($request->validated());

        return new WarehouseResource($warehouse);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        $this->authorize('view', $warehouse);

        return new WarehouseResource($warehouse);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('delete', $warehouse);

        $warehouse->delete();

        return response()->json(['message' => 'Warehouse deleted.']);
    }
}
