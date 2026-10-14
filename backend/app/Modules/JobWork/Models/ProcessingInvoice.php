<?php
namespace App\Modules\JobWork\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class ProcessingInvoice extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','invoice_no','customer_id','job_work_order_id','qty','rate','amount','date','status','created_by'];
    protected $casts = ['qty'=>'decimal:4','rate'=>'decimal:4','amount'=>'decimal:4','date'=>'date'];
}
