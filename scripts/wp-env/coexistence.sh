#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WP_CMD=(npx wp-env run cli wp)
THEME="wdk-shared-runtime-theme"
BASE_URL="http://127.0.0.1:8888"
ASSERT_DIR="wp-content/plugins/wp-development-kit/tests/fixtures/wp-env"

run_wp() {
  "${WP_CMD[@]}" "$@"
}

require_output() {
  local haystack="$1"
  local needle="$2"

  if [[ "${haystack}" != *"${needle}"* ]]; then
    printf 'Expected output to contain "%s".\n' "${needle}" >&2
    exit 1
  fi
}

require_absent() {
  local haystack="$1"
  local needle="$2"

  if [[ "${haystack}" == *"${needle}"* ]]; then
    printf 'Expected output to not contain "%s".\n' "${needle}" >&2
    exit 1
  fi
}

assert_clean_response() {
  local html="$1"

  require_absent "${html}" "Fatal error"
  require_absent "${html}" "Parse error"
  require_absent "${html}" "Cannot redeclare"
  require_absent "${html}" "Uncaught Error"
}

prepare_wordpress() {
  run_wp rewrite structure '/%postname%/' --hard >/dev/null
  run_wp option update blog_public 0 >/dev/null
  run_wp theme activate "${THEME}" >/dev/null
}

same_version_scenario() {
  run_wp plugin deactivate wdk-fixture-legacy wdk-fixture-eager >/dev/null 2>&1 || true
  run_wp plugin activate wp-development-kit wdk-fixture-alpha wdk-fixture-beta >/dev/null
  prepare_wordpress
  run_wp eval-file "${ASSERT_DIR}/assert-same-version.php"

  local html
  html="$(curl -fsS "${BASE_URL}/wdk-coexistence/")"
  assert_clean_response "${html}"
  require_output "${html}" "WDK_SHARED_RUNTIME_THEME"
  require_output "${html}" "THEME=theme-template-active"
  require_output "${html}" "ALPHA=alpha-ok"
  require_output "${html}" "SECONDARY=beta-ok"
  require_output "${html}" "VERSION=0.4.0"
}

mixed_version_scenario() {
  run_wp plugin deactivate wdk-fixture-beta wdk-fixture-eager >/dev/null 2>&1 || true
  run_wp plugin activate wp-development-kit wdk-fixture-alpha wdk-fixture-legacy >/dev/null
  prepare_wordpress
  run_wp eval-file "${ASSERT_DIR}/assert-mixed-version.php"

  local html
  html="$(curl -fsS "${BASE_URL}/wdk-coexistence/")"
  assert_clean_response "${html}"
  require_output "${html}" "ALPHA=alpha-ok"
  require_output "${html}" "SECONDARY=legacy-ok"
  require_output "${html}" "VERSION=0.4.0"
}

legacy_eager_scenario() {
  run_wp plugin deactivate wdk-fixture-beta wdk-fixture-legacy >/dev/null 2>&1 || true
  run_wp plugin activate wp-development-kit wdk-fixture-alpha wdk-fixture-eager >/dev/null
  prepare_wordpress
  run_wp eval-file "${ASSERT_DIR}/assert-legacy-eager.php"

  local html
  html="$(curl -fsS "${BASE_URL}/wdk-coexistence/")"
  assert_clean_response "${html}"
  require_output "${html}" "ALPHA=alpha-ok"
  require_output "${html}" "THEME=theme-template-active"
}

same_version_scenario
mixed_version_scenario
legacy_eager_scenario

printf 'WDK coexistence scenarios passed.\n'
