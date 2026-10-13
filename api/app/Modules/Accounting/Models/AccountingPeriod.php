<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'financial_year_id', 'name', 'start_date', 'end_date', 'status',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
