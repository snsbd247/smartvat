<?php
namespace App\Modules\Vat\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class VatNoteMapping extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','transaction_type_code','tax_treatment_code','vat_rate_code',
        'category_code','vat_return_note_id','subform','effective_from','rule_version',
        'approval_status','verification_status','verified_by'];
    protected $casts = ['effective_from'=>'date'];
}
