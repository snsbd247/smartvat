<?php

namespace App\Modules\Notification\Services;

use App\Modules\Notification\Models\AppNotification;
use App\Shared\Tenancy\CurrentCompany;

/**
 * Persisted in-app notifications. A null user_id makes a notification company-wide.
 */
class NotificationService
{
    public function __construct(private CurrentCompany $current) {}

    public function notify(string $type, string $title, ?string $message = null, ?string $link = null, ?int $userId = null, ?int $companyId = null): AppNotification
    {
        return AppNotification::create([
            'company_id' => $companyId ?? $this->current->idOrFail(),
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
        ]);
    }
}
