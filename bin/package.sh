#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="vs-relink"
VERSION="$(grep -m1 'VS_RELINK_VERSION' "${ROOT}/${SLUG}.php" | sed "s/.*'\([^']*\)'.*/\1/")"
OUT="${ROOT}/dist/${SLUG}-${VERSION}.zip"

mkdir -p "${ROOT}/dist"
rm -f "${OUT}"

cd "${ROOT}"
composer install --no-dev --optimize-autoloader --no-interaction

zip -r "${OUT}" . \
  -x './.git/*' \
  -x './dist/*' \
  -x './.github/*' \
  -x './composer.lock' \
  -x './.phpunit.cache/*' \
  -x './vendor/verysimple/vs-core/vendor/*'

echo "Built ${OUT}"
