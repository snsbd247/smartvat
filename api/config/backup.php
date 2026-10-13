<?php

return [
    // Local staging/storage directory for backup artifacts.
    'path' => storage_path('app/backups'),

    // Keep this many days of backups.
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

    // Encryption key for backup artifacts. MUST be kept separate from the artifacts
    // themselves (e.g. a different secret store); defaults to APP_KEY for dev only.
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY', env('APP_KEY')),

    // Storage driver: 'local' (default) or 'google'.
    'driver' => env('BACKUP_DRIVER', 'local'),

    'google' => [
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'credentials_json' => env('GOOGLE_DRIVE_CREDENTIALS'), // path to service-account JSON
    ],
];
