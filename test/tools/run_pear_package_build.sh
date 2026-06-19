#!/usr/bin/env bash
# Run Phing (PHAR or vendor/bin/phing) with Composer PEAR libraries on the include path.
#
# Why Phing 2.17.x (not Phing 3.x)?
# - generator/pear/BuildPropelGenPEARPackageTask.php and runtime/pear/BuildPropelPEARPackageTask.php
#   extend Phing 2 APIs (MatchingTask, PhingFile, phing/tasks/ext/pearpackage/*).
# - Phing 3 moved to new class namespaces and removed several Phing 2 task/extension paths.
# - pear/pear_packagefilemanager still requires PHP 7.4 (curly-brace string offsets removed in PHP 8.0).
# - Phing 3.x requires PHP 8.1+, so it cannot run in the same environment as PackageFileManager2 today.
#
# Why error_reporting=22517?
# - On PHP 7.4 this is E_ALL & ~E_WARNING & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED.
# - Phing 2 installs a custom PHP error handler and aborts the build on those levels.
# - Legacy Phing 2 and PEAR code emit many non-fatal warnings/deprecations that are safe to ignore
#   for packaging; suppressing them avoids false build failures.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
# E_ALL (32767) - E_DEPRECATED (8192) - E_STRICT (2048) - E_NOTICE (8) - E_WARNING (2)
PEAR_BUILD_ERROR_REPORTING=22517

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

exec php -d error_reporting="${PEAR_BUILD_ERROR_REPORTING}" \
    -d display_errors=0 \
    -d auto_prepend_file="$REPO_ROOT/vendor/autoload.php" \
    -d "include_path=${PEAR_PATH}:." \
    "$PHING_BIN" "$@"
