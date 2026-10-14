<?php

namespace App\Modules\Inventory\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'type', 'txn_no', 'date', 'warehouse_id', 'to_warehouse_id',
        'reference', 'narration', 'status', 'created_by',
    ];

    protected $casts = ['date' => 'date'];

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_ISSUE = 'issue';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_ADJUSTMENT = 'adjustment';
}
