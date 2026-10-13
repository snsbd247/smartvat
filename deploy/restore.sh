#!/usr/bin/env bash
# PLASTEX ERP — restore the latest verified backup into the database.
# Usage: bash deploy/restore.sh                (restores most recent verified backup)
#
# This decrypts + decompresses the artifact via the app and writes the raw dump, then
# loads it. For MySQL it runs `mysql < dump.sql`; for SQLite it replaces the DB file.
set -euo pipefail

API_DIR="$(cd "$(dirname "$0")/../api" && pwd)"
cd "$API_DIR"

echo "==> Restoring the most recent verified backup via the app"
php artisan tinker --execute='
  $job = \App\Modules\Backup\Models\BackupJob::where("status","success")->where("verified",true)->orderByDesc("finished_at")->first();
  abort_unless($job, 1, "No verified backup found.");
  $target = storage_path("app/backups/restore_dump.out");
  app(\App\Modules\Backup\Services\BackupService::class)->restore($job, $target);
  echo "Restored artifact {$job->filename} -> {$target}\n";
'

DUMP="storage/app/backups/restore_dump.out"
CONN="$(php -r 'echo config("database.default");' 2>/dev/null || echo mysql)"

if [ "$CONN" = "mysql" ]; then
  echo "==> Loading dump into MySQL (set DB_* in .env first)"
  DB=$(php -r 'echo config("database.connections.mysql.database");')
  USER=$(php -r 'echo config("database.connections.mysql.username");')
  mysql -u "$USER" -p "$DB" < "$DUMP"
else
  echo "==> SQLite: replacing the database file"
  DBFILE=$(php -r 'echo config("database.connections.sqlite.database");')
  cp "$DUMP" "$DBFILE"
fi

echo "==> Restore complete. Verify with: php artisan migrate:status"
