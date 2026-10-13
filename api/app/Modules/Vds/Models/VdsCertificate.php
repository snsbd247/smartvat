<?php
namespace App\Modules\Vds\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class VdsCertificate extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','vds_deduction_id','certificate_no','date'];
    protected $casts = ['date'=>'date'];
}
