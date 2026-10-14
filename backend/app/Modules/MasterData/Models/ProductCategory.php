<?php

namespace App\Modules\MasterData\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'parent_id', 'status'];
}
