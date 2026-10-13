<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    use BelongsToCompany;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'company_id', 'code', 'name', 'type', 'normal_balance',
        'parent_id', 'is_postable', 'status',
    ];

    protected $casts = ['is_postable' => 'boolean'];

    public const DEBIT_TYPES = ['asset', 'expense'];

    public static function normalBalanceFor(string $type): string
    {
        return in_array($type, self::DEBIT_TYPES, true) ? 'debit' : 'credit';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
