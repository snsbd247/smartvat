<?php
namespace App\Modules\Vat\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class VatReturnNote extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','code','title','subform','effective_from','legal_ref','verification_status'];
    protected $casts = ['effective_from'=>'date'];
}
