#!/usr/bin/env bash
# Build the SPA and wire it into Laravel's public dir so Apache (XAMPP) can serve
# the whole app at /irlvat. Re-run this after any frontend change.
#
#   bash others/deploy/build-local.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WEB="$ROOT/frontend"
PUBLIC="$ROOT/backend/public"
VIEWS="$ROOT/backend/resources/views"

echo "==> Building frontend (base /irlvat/)"
cd "$WEB"
npm run build

echo "==> Publishing built assets into backend/public"
rm -rf "$PUBLIC/assets"
cp -r "$WEB/dist/assets" "$PUBLIC/assets"
# Static files referenced by index.html (favicon, icons, etc.)
for f in favicon.svg icons.svg; do
  [ -f "$WEB/dist/$f" ] && cp "$WEB/dist/$f" "$PUBLIC/$f" || true
done

echo "==> Installing SPA entry as a Laravel view (spa.blade.php)"
cp "$WEB/dist/index.html" "$VIEWS/spa.blade.php"

echo "==> Done. Browse at:  http://localhost/irlvat   and   http://160.25.9.139/irlvat"
