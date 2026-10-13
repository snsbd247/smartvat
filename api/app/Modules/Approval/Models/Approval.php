<?php

namespace App\Modules\Approval\Models;

use App\Models\User;
use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'approvable_type', 'approvable_id', 'document_no', 'amount',
        'status', 'note', 'requested_by', 'acted_by', 'acted_at',
    ];

    protected $casts = ['amount' => 'decimal:4', 'acted_at' => 'datetime'];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function actedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    /** Short type label, e.g. "purchase_order". */
    public function typeLabel(): string
    {
        return \Illuminate\Support\Str::snake(class_basename($this->approvable_type));
    }
}
