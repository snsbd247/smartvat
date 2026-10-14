<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAccountMap extends Model
{
    use BelongsToCompany;

    protected $table = 'company_account_map';

    protected $fillable = ['company_id', 'key', 'account_id'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
