<?php

namespace App\Shared\Mail;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Config;

/**
 * Applies a company's saved SMTP settings (Settings "email" group) to the mail
 * config at runtime. When no host is configured the default mailer (log) is kept,
 * so the app never fails to "send" — it just logs until SMTP is provided.
 */
class MailConfigurator
{
    public static function apply(?int $companyId): void
    {
        if ($companyId === null) {
            return;
        }

        $setting = Setting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('group', 'email')
            ->first();

        $payload = $setting?->payload ?? [];
        $host = $payload['host'] ?? null;

        if (empty($host)) {
            return; // keep the default (log) mailer
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', (int) ($payload['port'] ?? 587));
        Config::set('mail.mailers.smtp.username', $payload['username'] ?? null);
        Config::set('mail.mailers.smtp.password', $payload['password'] ?? null);
        $enc = $payload['encryption'] ?? 'tls';
        Config::set('mail.mailers.smtp.encryption', $enc === 'none' ? null : $enc);

        if (! empty($payload['from_address'])) {
            Config::set('mail.from.address', $payload['from_address']);
            Config::set('mail.from.name', $payload['from_name'] ?? config('app.name'));
        }
    }
}
