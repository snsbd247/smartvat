<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Services\CsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImportController extends Controller
{
    public function __construct(private CsvImportService $service) {}

    /** Column template (header) for an entity. */
    public function template(string $entity): JsonResponse
    {
        $this->authorize('configure-company');

        return response()->json(['entity' => $entity, 'header' => $this->service->template($entity)]);
    }

    public function run(Request $request, string $entity): JsonResponse
    {
        $this->authorize('configure-company');

        abort_unless(in_array($entity, $this->service->entities(), true), 404);

        $data = $request->validate([
            'csv' => ['required_without:file', 'string'],
            'file' => ['required_without:csv', 'file', 'max:5120'], // 5 MB
        ]);

        $csv = isset($data['file'])
            ? file_get_contents($request->file('file')->getRealPath())
            : $data['csv'];

        return response()->json($this->service->import($entity, (string) $csv), 201);
    }
}
