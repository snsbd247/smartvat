<?php
namespace App\Modules\JobWork\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class JobWorkOrder extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','order_no','manufacturing_contract_id','customer_id','product_id','warehouse_id','planned_qty','status','created_by'];
    protected $casts = ['planned_qty'=>'decimal:4'];
}
