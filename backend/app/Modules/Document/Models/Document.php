<?php

namespace App\Modules\Document\Models;

use App\Models\User;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'title', 'filename', 'path', 'mime', 'size',
        'category', 'version', 'group_key', 'attachable_type', 'attachable_id', 'uploaded_by',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
