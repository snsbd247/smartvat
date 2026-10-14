<?php

namespace App\Modules\Purchase\Models;

use App\Shared\Audit\Auditable;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'type', 'contact_person', 'phone', 'email',
        'address', 'bin', 'vat_reg_no', 'status',
    ];
}
