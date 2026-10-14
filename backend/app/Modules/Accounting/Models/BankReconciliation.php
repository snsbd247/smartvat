<?php
namespace App\Modules\Accounting\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class BankReconciliation extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','account_id','statement_date','statement_balance','book_balance','reconciled_balance','difference','created_by'];
    protected $casts = ['statement_date'=>'date','statement_balance'=>'decimal:4','book_balance'=>'decimal:4','reconciled_balance'=>'decimal:4','difference'=>'decimal:4'];
}
