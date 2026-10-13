<?php

namespace App\Modules\Reporting;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Reusable report renderer. One ReportDefinition → HTML, PDF or XLSX. Used by every
 * report so output stays consistent; generated files are streamed through authorized
 * routes, never written to a public path.
 *
 * PDF uses dompdf (pure PHP, runs anywhere incl. CI). Browsershot/headless-Chromium can
 * be swapped in later for pixel-perfect statutory (Mushak) form layouts.
 */
class ReportEngine
{
    public function toHtml(ReportDefinition $def): string
    {
        return view('reports.generic', ['r' => $def])->render();
    }

    public function toPdf(ReportDefinition $def): string
    {
        return Pdf::loadHtml($this->toHtml($def))->setPaper('a4', 'landscape')->output();
    }

    public function toXlsx(ReportDefinition $def): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Title + header row (PhpSpreadsheet 5.x: [column, row] coordinates).
        $sheet->setCellValue('A1', $def->title);
        $colIndex = 1;
        foreach ($def->columns as $c) {
            $sheet->setCellValue([$colIndex, 3], $c['label']);
            $colIndex++;
        }

        // Data rows.
        $rowIndex = 4;
        foreach ($def->rows as $row) {
            $colIndex = 1;
            foreach ($def->columns as $c) {
                $value = $row[$c['key']] ?? '';
                $sheet->setCellValue([$colIndex, $rowIndex], is_scalar($value) ? $value : json_encode($value));
                $colIndex++;
            }
            $rowIndex++;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rpt').'.xlsx';
        (new Xlsx($spreadsheet))->save($tmp);
        $contents = file_get_contents($tmp);
        @unlink($tmp);

        return $contents;
    }
}
