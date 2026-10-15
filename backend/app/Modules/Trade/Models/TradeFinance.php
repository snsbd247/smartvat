<?php

namespace App\Modules\Trade\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class TradeFinance extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'tf_no', 'kind', 'party', 'bank', 'ref_number', 'currency', 'amount', 'issue_date', 'expiry_date', 'status', 'note', 'created_by'];
    protected $casts = ['amount' => 'decimal:4', 'issue_date' => 'date', 'expiry_date' => 'date'];
}
