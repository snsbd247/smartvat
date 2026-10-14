<?php
namespace App\Modules\Purchase\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class PurchaseReturn extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','return_no','supplier_id','warehouse_id','date','amount','reference','status','created_by'];
    protected $casts = ['date'=>'date','amount'=>'decimal:4'];
    public function lines(): HasMany { return $this->hasMany(PurchaseReturnLine::class); }
}
