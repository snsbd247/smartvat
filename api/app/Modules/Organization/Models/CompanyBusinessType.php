<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyBusinessType extends Model
{
    protected $fillable = ['company_id', 'type', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];
}
