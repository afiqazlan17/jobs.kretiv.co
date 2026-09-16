#!/usr/bin/env bash
# Builds vendor.zip and build.zip for manual upload to Exabytes cPanel —
# see DEPLOYMENT.md / CLAUDE.md for why this can't run on the server itself.
set -euo pipefail
cd "$(dirname "$0")/.."

OUT_DIR="${1:-./deploy-package}"
mkdir -p "$OUT_DIR"

echo "==> composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader --prefer-dist

echo "==> stripping .git dirs and non-runtime test/doc dirs from vendor/"
find vendor -iname ".git" -type d -prune -exec rm -rf {} +
find vendor -type d \( -iname "tests" -o -iname "test" -o -iname "docs" -o -iname ".github" -o -iname "benchmarks" -o -iname "examples" \) -prune -exec rm -rf {} +

echo "==> npm ci && npm run build"
npm ci
npm run build

echo "==> zipping vendor/"
rm -f "$OUT_DIR/vendor.zip"
zip -rq "$OUT_DIR/vendor.zip" vendor

echo "==> zipping public/build/"
rm -f "$OUT_DIR/build.zip"
zip -rq "$OUT_DIR/build.zip" public/build

echo "==> restoring dev dependencies locally"
composer install

echo
echo "Done. Upload these to the server (see DEPLOYMENT.md):"
ls -lh "$OUT_DIR"/*.zip
