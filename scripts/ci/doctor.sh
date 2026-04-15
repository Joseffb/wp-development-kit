#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

require_docker=0
require_node=0
require_wp_env=0
require_act=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --require-docker)
      require_docker=1
      shift
      ;;
    --require-node)
      require_node=1
      shift
      ;;
    --require-wp-env)
      require_node=1
      require_wp_env=1
      shift
      ;;
    --require-act)
      require_act=1
      shift
      ;;
    *)
      printf 'Unknown doctor option: %s\n' "$1" >&2
      exit 1
      ;;
  esac
done

require_command() {
  local name="$1"
  if ! command -v "${name}" >/dev/null 2>&1; then
    printf 'Missing required command: %s\n' "${name}" >&2
    exit 1
  fi
}

require_command php
require_command composer

if (( require_node == 1 )); then
  require_command node
  require_command npm
fi

if (( require_docker == 1 )); then
  require_command docker
  if ! docker info >/dev/null 2>&1; then
    printf 'Docker is installed but the daemon is not available.\n' >&2
    exit 1
  fi
fi

if (( require_act == 1 )); then
  require_command act
fi

if (( require_wp_env == 1 )); then
  if [[ ! -f "${ROOT_DIR}/package.json" ]]; then
    printf 'Missing package.json; wp-env prerequisites are unavailable.\n' >&2
    exit 1
  fi

  if ! node -e "const pkg=require('${ROOT_DIR}/package.json'); const deps={...(pkg.dependencies||{}),...(pkg.devDependencies||{})}; process.exit(deps['@wordpress/env'] ? 0 : 1);" >/dev/null 2>&1; then
    printf '@wordpress/env is not declared in package.json.\n' >&2
    exit 1
  fi
fi

printf 'WDK CI doctor checks passed.\n'
