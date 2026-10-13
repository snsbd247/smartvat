<?php
namespace App\Modules\Accounting\Models;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Budget extends Model {
    use BelongsToCompany;
    protected $fillable = ['company_id','name','financial_year_id','status','created_by'];
    public function lines(): HasMany { return $this->hasMany(BudgetLine::class); }
}
