<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyModule extends Model
{
    protected $fillable = ['company_id', 'module_key', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];
}
