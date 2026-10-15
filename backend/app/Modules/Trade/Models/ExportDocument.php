<?php

namespace App\Modules\Trade\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ExportDocument extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'doc_no', 'doc_type', 'party', 'reference', 'date', 'status', 'note', 'created_by'];
    protected $casts = ['date' => 'date'];
}
