<?php
namespace App\Modules\Vds\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class VdsRule extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','service_category','rate','effective_from','legal_ref','approval_status','verification_status','verified_by'];
    protected $casts = ['rate'=>'decimal:4','effective_from'=>'date'];
}
