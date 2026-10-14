<?php
namespace App\Modules\Vat\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class VatTransactionType extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','code','name'];
}
