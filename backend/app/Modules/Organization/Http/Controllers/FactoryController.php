<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\StoreFactoryRequest;
use App\Modules\Organization\Http\Requests\UpdateFactoryRequest;
use App\Modules\Organization\Http\Resources\FactoryResource;
use App\Modules\Organization\Models\Factory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FactoryController extends Controller
{
    // Policy authorization is applied per action below. Tenant scoping is enforced
    // separately by the CompanyScope global scope (reads) and route-model binding.

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Factory::class);

        $factories = Factory::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate($request->integer('per_page', 25));

        return FactoryResource::collection($factories);
    }

    public function store(StoreFactoryRequest $request): FactoryResource
    {
        $this->authorize('create', Factory::class);

        // company_id is stamped automatically by BelongsToCompany from the tenant context.
        $factory = Factory::create($request->validated());

        return new FactoryResource($factory);
    }

    public function show(Factory $factory): FactoryResource
    {
        $this->authorize('view', $factory);

        return new FactoryResource($factory);
    }

    public function update(UpdateFactoryRequest $request, Factory $factory): FactoryResource
    {
        $this->authorize('update', $factory);

        $factory->update($request->validated());

        return new FactoryResource($factory);
    }

    public function destroy(Factory $factory): \Illuminate\Http\JsonResponse
    {
        $this->authorize('delete', $factory);

        $factory->delete();

        return response()->json(['message' => 'Factory deleted.']);
    }
}
