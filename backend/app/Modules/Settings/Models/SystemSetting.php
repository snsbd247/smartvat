<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * System-wide settings (NOT tenant-scoped). The value is encrypted at rest, so
 * credentials such as the Google Drive service-account JSON are never stored
 * in plaintext. Use get()/put() for convenient access.
 */
class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'encrypted:array'];

    public static function get(string $key, array $default = []): array
    {
        return static::where('key', $key)->first()?->value ?? $default;
    }

    public static function put(string $key, array $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
