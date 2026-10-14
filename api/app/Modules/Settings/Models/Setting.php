<?php

namespace App\Modules\Settings\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'group', 'payload'];

    protected $casts = ['payload' => 'array'];
}
