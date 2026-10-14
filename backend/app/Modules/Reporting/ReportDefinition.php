<?php

namespace App\Modules\Reporting;

/**
 * A rendering-agnostic description of a report: the same definition drives the
 * JSON, HTML, PDF and XLSX outputs so every report looks and exports consistently.
 */
class ReportDefinition
{
    /**
     * @param  array<int, array{key:string, label:string, align?:string}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $totals
     */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows,
        public array $meta = [],
        public array $totals = [],
        public ?string $subtitle = null,
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'columns' => $this->columns,
            'rows' => $this->rows,
            'totals' => $this->totals,
            'meta' => $this->meta,
        ];
    }
}
