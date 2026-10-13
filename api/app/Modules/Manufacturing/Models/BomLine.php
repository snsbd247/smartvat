<?php

namespace App\Modules\Manufacturing\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class BomLine extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'bom_id', 'component_product_id', 'qty'];

    protected $casts = ['qty' => 'decimal:4'];
}
