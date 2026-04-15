#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

"${ROOT_DIR}/scripts/ci/doctor.sh"

cd "${ROOT_DIR}"

"${ROOT_DIR}/scripts/ci/php.sh"
"${ROOT_DIR}/scripts/ci/wp-env.sh"
"${ROOT_DIR}/scripts/ci/coexistence.sh"
