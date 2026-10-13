#!/usr/bin/env bash
# Build the SPA and wire it into Laravel's public dir so Apache (XAMPP) can serve
# the whole app at /irlvat. Re-run this after any frontend change.
#
#   bash deploy/build-local.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WEB="$ROOT/web"
PUBLIC="$ROOT/api/public"
VIEWS="$ROOT/api/resources/views"

echo "==> Building frontend (base /irlvat/)"
cd "$WEB"
npm run build

echo "==> Publishing built assets into api/public"
rm -rf "$PUBLIC/assets"
cp -r "$WEB/dist/assets" "$PUBLIC/assets"
# Static files referenced by index.html (favicon, icons, etc.)
for f in favicon.svg icons.svg; do
  [ -f "$WEB/dist/$f" ] && cp "$WEB/dist/$f" "$PUBLIC/$f" || true
done

echo "==> Installing SPA entry as a Laravel view (spa.blade.php)"
cp "$WEB/dist/index.html" "$VIEWS/spa.blade.php"

echo "==> Done. Browse at:  http://localhost/irlvat   and   http://160.25.9.139/irlvat"
