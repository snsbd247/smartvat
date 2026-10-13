<?php

namespace App\Modules\Sales\Models;

use App\Shared\Audit\Auditable;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'contact_person', 'phone', 'email',
        'address', 'country', 'bin', 'vat_reg_no', 'status',
    ];
}
