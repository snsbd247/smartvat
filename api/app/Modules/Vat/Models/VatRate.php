<?php
namespace App\Modules\Vat\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class VatRate extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','code','rate','effective_from','status'];
    protected $casts = ['rate'=>'decimal:4','effective_from'=>'date'];
}
