<?php

namespace App\Modules\Purchase\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'pi_no', 'supplier_id', 'purchase_order_id', 'goods_receipt_note_id',
        'type', 'date', 'supplier_invoice_no', 'subtotal', 'total', 'narration',
        'status', 'created_by',
    ];

    protected $casts = ['date' => 'date', 'subtotal' => 'decimal:4', 'total' => 'decimal:4'];

    public const TYPE_MATERIAL = 'material';

    public const TYPE_SERVICE = 'service';

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class);
    }
}
