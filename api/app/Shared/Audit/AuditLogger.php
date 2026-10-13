<?php

namespace App\Shared\Audit;

use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Central writer for the audit trail. Captures who did what, to which record,
 * within which company, with before/after values for sensitive changes.
 */
class AuditLogger
{
    public static function log(
        string $event,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?int $companyId = null,
    ): void {
        $request = request();

        AuditLog::create([
            'company_id' => $companyId ?? app(CurrentCompany::class)->id(),
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
