<?php
namespace App\Modules\Vds\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class VdsDeduction extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','deduction_no','supplier_id','service_category','base_amount','rate','vds_amount','date','source_type','source_id','status','created_by'];
    protected $casts = ['base_amount'=>'decimal:4','rate'=>'decimal:4','vds_amount'=>'decimal:4','date'=>'date'];
}
