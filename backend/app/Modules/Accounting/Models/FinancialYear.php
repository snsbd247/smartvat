<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialYear extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'start_date', 'end_date', 'status'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class);
    }
}
