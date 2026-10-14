<?php

namespace App\Modules\Sales\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'so_no', 'customer_id', 'kind', 'currency', 'exchange_rate',
        'date', 'status', 'reference', 'total', 'approved_by', 'approved_at', 'created_by',
    ];

    protected $casts = ['date' => 'date', 'approved_at' => 'datetime', 'exchange_rate' => 'decimal:8', 'total' => 'decimal:4'];

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }
}
