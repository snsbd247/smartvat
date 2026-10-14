<?php

namespace App\Modules\Sales\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryChallan extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'challan_no', 'customer_id', 'sales_invoice_id', 'warehouse_id',
        'date', 'reference', 'status', 'cogs_value', 'created_by',
    ];

    protected $casts = ['date' => 'date', 'cogs_value' => 'decimal:4'];

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryChallanLine::class);
    }
}
