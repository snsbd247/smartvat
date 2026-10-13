<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'journal_entry_id', 'account_id', 'cost_center_id',
        'party_type', 'party_id', 'debit', 'credit', 'currency', 'fx_rate', 'narration',
        'reconciled', 'reconciled_at',
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
        'fx_rate' => 'decimal:8',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
