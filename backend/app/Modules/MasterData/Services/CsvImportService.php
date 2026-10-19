<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Product;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Sales\Models\Customer;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Bulk CSV import for master data. First row is the header; rows are matched by a
 * unique "code" per company (existing codes are skipped, not overwritten). Returns
 * a per-file summary with row-level errors so a partial file still imports cleanly.
 */
class CsvImportService
{
    /** entity => [required header columns, allowed columns] */
    private const SCHEMA = [
        'products' => [
            'required' => ['code', 'name'],
            'allowed' => ['code', 'name', 'type', 'base_uom', 'costing_method'],
            'defaults' => ['type' => 'raw', 'base_uom' => 'PCS', 'costing_method' => 'weighted_average', 'status' => 'active'],
        ],
        'customers' => [
            'required' => ['code', 'name'],
            'allowed' => ['code', 'name', 'contact_person', 'phone', 'email', 'address', 'country', 'bin', 'vat_reg_no'],
            'defaults' => ['status' => 'active'],
        ],
        'suppliers' => [
            'required' => ['code', 'name'],
            'allowed' => ['code', 'name', 'type', 'contact_person', 'phone', 'email', 'address', 'bin', 'vat_reg_no'],
            'defaults' => ['status' => 'active'],
        ],
    ];

    public function __construct(private CurrentCompany $current) {}

    public function entities(): array
    {
        return array_keys(self::SCHEMA);
    }

    public function template(string $entity): string
    {
        return implode(',', $this->schema($entity)['allowed']);
    }

    public function import(string $entity, string $csv): array
    {
        $schema = $this->schema($entity);
        $companyId = $this->current->idOrFail();

        $rows = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $csv)), fn ($l) => $l !== ''));
        if (count($rows) < 2) {
            throw new InvalidArgumentException('Provide a header row and at least one data row.');
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($rows)));
        foreach ($schema['required'] as $col) {
            if (! in_array($col, $header, true)) {
                throw new InvalidArgumentException("Missing required column: {$col}");
            }
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($entity, $rows, $header, $schema, $companyId, &$created, &$skipped, &$errors) {
            foreach ($rows as $i => $line) {
                $cols = str_getcsv($line);
                $row = [];
                foreach ($header as $idx => $name) {
                    if (in_array($name, $schema['allowed'], true)) {
                        $row[$name] = isset($cols[$idx]) ? trim($cols[$idx]) : null;
                    }
                }

                if (empty($row['code']) || empty($row['name'])) {
                    $errors[] = ['row' => $i + 2, 'message' => 'code and name are required'];
                    continue;
                }

                $model = $this->modelFor($entity);
                if ($model::where('company_id', $companyId)->where('code', $row['code'])->exists()) {
                    $skipped++;
                    continue;
                }

                $model::create(array_merge($schema['defaults'], $row, ['company_id' => $companyId]));
                $created++;
            }
        });

        return ['entity' => $entity, 'created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function schema(string $entity): array
    {
        if (! isset(self::SCHEMA[$entity])) {
            throw new InvalidArgumentException("Unsupported import entity: {$entity}");
        }

        return self::SCHEMA[$entity];
    }

    private function modelFor(string $entity): string
    {
        return match ($entity) {
            'products' => Product::class,
            'customers' => Customer::class,
            'suppliers' => Supplier::class,
        };
    }
}
