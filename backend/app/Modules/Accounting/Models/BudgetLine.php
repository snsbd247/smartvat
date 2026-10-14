<?php
namespace App\Modules\Accounting\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class BudgetLine extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','budget_id','account_id','amount'];
    protected $casts = ['amount'=>'decimal:4'];
}
