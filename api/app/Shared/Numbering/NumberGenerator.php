<?php

namespace App\Shared\Numbering;

use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;

/**
 * Generates gapless, per-company document numbers atomically. The sequence row is
 * locked for update inside the caller's transaction so concurrent requests can't
 * produce duplicates.
 */
class NumberGenerator
{
    public function __construct(private CurrentCompany $current) {}

    public function next(string $key, string $defaultPrefix = ''): string
    {
        $companyId = $this->current->idOrFail();

        return DB::transaction(function () use ($companyId, $key, $defaultPrefix) {
            $row = DB::table('number_sequences')
                ->where('company_id', $companyId)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('number_sequences')->insert([
                    'company_id' => $companyId,
                    'key' => $key,
                    'prefix' => $defaultPrefix,
                    'next_no' => 1,
                    'padding' => 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = DB::table('number_sequences')
                    ->where('company_id', $companyId)->where('key', $key)
                    ->lockForUpdate()->first();
            }

            $number = (int) $row->next_no;

            DB::table('number_sequences')
                ->where('id', $row->id)
                ->update(['next_no' => $number + 1, 'updated_at' => now()]);

            $prefix = $row->prefix !== '' ? $row->prefix : $defaultPrefix;

            return $prefix.str_pad((string) $number, (int) $row->padding, '0', STR_PAD_LEFT);
        });
    }
}
