<?php

namespace App\Modules\Mis\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mis\Services\MisService;
use Illuminate\Http\JsonResponse;

class MisController extends Controller
{
    public function __construct(private MisService $mis) {}

    public function executive(): JsonResponse
    {
        $this->authorize('mis-view');

        return response()->json($this->mis->executive());
    }

    public function approvals(): JsonResponse
    {
        $this->authorize('mis-view');

        return response()->json(['pending' => $this->mis->approvals()]);
    }
}
