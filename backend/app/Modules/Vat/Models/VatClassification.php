<?php
namespace App\Modules\Vat\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class VatClassification extends Model {
    use BelongsToCompany;
    protected $table = 'vat_transaction_classifications';
    protected $fillable = ['company_id','source_type','source_id','line_ref','transaction_type_code',
        'tax_treatment_code','vat_rate_code','category_code','taxable_amount','vat_amount',
        'vat_return_note_id','subform','rule_version','status','date'];
    protected $casts = ['taxable_amount'=>'decimal:4','vat_amount'=>'decimal:4','date'=>'date'];
}
