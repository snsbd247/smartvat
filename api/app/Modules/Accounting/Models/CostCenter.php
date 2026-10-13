<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'status'];
}
