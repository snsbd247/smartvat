<?php
namespace App\Modules\Vds\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class VdsTreasuryDeposit extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','challan_no','date','amount','paid_from_account_id','reference','created_by'];
    protected $casts = ['amount'=>'decimal:4','date'=>'date'];
}
