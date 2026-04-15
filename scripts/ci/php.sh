#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

"${ROOT_DIR}/scripts/ci/doctor.sh"

cd "${ROOT_DIR}"

composer validate --no-check-publish
composer install --no-interaction --prefer-dist
composer lint
composer test
