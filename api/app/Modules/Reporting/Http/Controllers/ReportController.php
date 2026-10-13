<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\ReportEngine;
use App\Modules\Reporting\ReportRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single secured entry point for all reports. Output format is chosen per request;
 * generated PDF/XLSX are streamed through this authorized, tenant-scoped route — never
 * exposed at a public or predictable URL.
 */
class ReportController extends Controller
{
    public function __construct(
        private ReportRegistry $registry,
        private ReportEngine $engine,
    ) {}

    public function index(): Response
    {
        $this->authorize('reports-view');

        return response()->json(['reports' => $this->registry->keys()]);
    }

    public function show(Request $request, string $key): Response
    {
        $this->authorize('reports-view');

        $definition = $this->registry->build($key, $request->query());
        $format = $request->query('format', 'json');

        return match ($format) {
            'json' => response()->json($definition->toArray()),
            'html' => response($this->engine->toHtml($definition))->header('Content-Type', 'text/html'),
            'pdf' => response($this->engine->toPdf($definition), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$key.'.pdf"',
            ]),
            'xlsx' => response($this->engine->toXlsx($definition), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$key.'.xlsx"',
            ]),
            default => response()->json(['message' => 'Unsupported format.'], 422),
        };
    }
}
