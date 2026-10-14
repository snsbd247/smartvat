<?php
namespace App\Modules\JobWork\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class ManufacturingContract extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','contract_no','customer_id','processing_rate','scrap_ownership','terms','date','status'];
    protected $casts = ['processing_rate'=>'decimal:4','date'=>'date'];
}
