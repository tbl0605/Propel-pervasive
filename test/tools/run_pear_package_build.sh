#!/usr/bin/env bash
# Run Phing (PHAR or vendor/bin/phing) with Composer PEAR libraries on the include path.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

if [[ ! -f "$REPO_ROOT/vendor/autoload.php" ]]; then
    echo "ERROR: Run composer install first (missing vendor/autoload.php)." >&2
    exit 1
fi

if [[ -n "${PHING_PHAR:-}" && -f "${PHING_PHAR}" ]]; then
    PHING_BIN="${PHING_PHAR}"
elif [[ -f "$REPO_ROOT/vendor/bin/phing" ]]; then
    PHING_BIN="$REPO_ROOT/vendor/bin/phing"
else
    echo "ERROR: No Phing PHAR (PHING_PHAR) or vendor/bin/phing found." >&2
    exit 1
fi

PEAR_PATH=""
if [[ -d "$REPO_ROOT/vendor/pear" ]]; then
    PEAR_PATH="$(find "$REPO_ROOT/vendor/pear" -mindepth 1 -maxdepth 1 -type d | paste -sd: - || true)"
fi

if [[ -z "$PEAR_PATH" ]]; then
    echo "ERROR: PEAR packages not found. Run composer install with dev dependencies." >&2
    exit 1
fi

exec php -d error_reporting=22517 \
    -d display_errors=0 \
    -d auto_prepend_file="$REPO_ROOT/vendor/autoload.php" \
    -d "include_path=${PEAR_PATH}:." \
    "$PHING_BIN" "$@"
