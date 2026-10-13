<?php

namespace App\Modules\Sales\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'return_no', 'customer_id', 'sales_invoice_id', 'warehouse_id',
        'date', 'amount', 'cogs_value', 'reference', 'status', 'created_by',
    ];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:4', 'cogs_value' => 'decimal:4'];

    public function lines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class);
    }
}
