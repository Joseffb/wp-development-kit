#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ACT_HOME="${ROOT_DIR}/.act-home"

"${ROOT_DIR}/scripts/ci/doctor.sh" --require-act --require-docker

resolve_docker_socket() {
  if [[ -n "${DOCKER_HOST:-}" && "${DOCKER_HOST}" == unix://* ]]; then
    printf '%s\n' "${DOCKER_HOST}"
    return
  fi

  if [[ -S "${HOME}/.docker/run/docker.sock" ]]; then
    printf 'unix://%s\n' "${HOME}/.docker/run/docker.sock"
    return
  fi

  printf 'unix:///var/run/docker.sock\n'
}

cd "${ROOT_DIR}"

mkdir -p \
  "${ACT_HOME}" \
  "${ACT_HOME}/.cache" \
  "${ACT_HOME}/.config" \
  "${ACT_HOME}/wp-env"

act push \
  -W .github/workflows/ci.yml \
  --concurrent-jobs 1 \
  --bind \
  --container-daemon-socket "$(resolve_docker_socket)" \
  --container-options "--group-add 0" \
  --env "HOME=${ACT_HOME}" \
  --env "XDG_CACHE_HOME=${ACT_HOME}/.cache" \
  --env "XDG_CONFIG_HOME=${ACT_HOME}/.config" \
  "$@"
