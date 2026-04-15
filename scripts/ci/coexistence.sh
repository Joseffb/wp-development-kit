#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

"${ROOT_DIR}/scripts/ci/doctor.sh" --require-docker --require-wp-env

cd "${ROOT_DIR}"

composer install --no-interaction --prefer-dist
npm ci

cleanup() {
  npm run wp-env:stop >/dev/null 2>&1 || true
}

trap cleanup EXIT

npm run wp-env:start
npm run wp-env:coexistence
