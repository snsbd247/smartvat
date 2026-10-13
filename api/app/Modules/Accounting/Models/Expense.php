<?php
namespace App\Modules\Accounting\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class Expense extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','expense_no','date','expense_account_id','paid_from_account_id','cost_center_id','amount','narration','created_by'];
    protected $casts = ['date'=>'date','amount'=>'decimal:4'];
}
