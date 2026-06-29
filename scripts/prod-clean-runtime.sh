#!/usr/bin/env bash
set -euo pipefail

QUIET=0
if [[ "${1:-}" == "--quiet" ]]; then
  QUIET=1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

log() {
  if [[ "$QUIET" -eq 0 ]]; then
    printf '%s\n' "$*"
  fi
}

clean_old_files() {
  local path="$1"
  local minutes="$2"
  shift 2

  if [[ ! -d "$path" ]]; then
    return
  fi

  log "Cleaning files older than ${minutes}m in ${path}"
  find "$path" -type f "$@" -mmin +"$minutes" -print -delete
}

# PhpWord creates temporary files while preparing DOCX/PDF documents.
# They must not be removed during generation, so keep a two-hour safety window.
clean_old_files "storage/framework/phpword-tmp" 120 -name 'php*'

# Generic app temp directory used by ApplicationTempDirectory/tempnam wrappers.
clean_old_files "storage/app/tmp" 120

# Laravel keeps runtime cache directories. Do not delete current framework files,
# only stale temporary files that are safe to regenerate.
clean_old_files "storage/framework/cache" 1440 -name '*.tmp'

log "Runtime cleanup complete."
