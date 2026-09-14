#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

ASSUME_YES=0
if [[ "${1:-}" == "--yes" ]]; then
    ASSUME_YES=1
    shift
fi

REQUESTED_RELEASE="${1:-}"
APP_ROOT="/var/www/schooltry"
PHP_FPM_SERVICE="php8.3-fpm"
HEALTHCHECK_URL="http://127.0.0.1/up"
RELEASES_DIR="$APP_ROOT/releases"
CURRENT_LINK="$APP_ROOT/current"
LOCK_FILE="/var/lock/schooltry-deploy.lock"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "A SchoolTry deployment or rollback is already running." >&2
    exit 1
fi

if [[ ! -L "$CURRENT_LINK" ]]; then
    echo "The current release symlink does not exist." >&2
    exit 1
fi

CURRENT_TARGET="$(readlink -f "$CURRENT_LINK")"

if [[ -n "$REQUESTED_RELEASE" ]]; then
    if [[ ! "$REQUESTED_RELEASE" =~ ^[A-Za-z0-9][A-Za-z0-9._-]*$ ]]; then
        echo "Invalid release ID." >&2
        exit 2
    fi
    TARGET="$(realpath -m "$RELEASES_DIR/$REQUESTED_RELEASE")"
else
    TARGET="$(
        find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d ! -name '.*.preparing' -printf '%T@ %p\n' \
            | sort -nr \
            | cut -d' ' -f2- \
            | while IFS= read -r candidate; do
                candidate="$(realpath -m "$candidate")"
                if [[ "$candidate" != "$CURRENT_TARGET" ]]; then
                    printf '%s\n' "$candidate"
                    break
                fi
            done
    )"
fi

if [[ -z "$TARGET" || "$TARGET" != "$RELEASES_DIR/"* || ! -d "$TARGET" ]]; then
    echo "No valid previous release is available." >&2
    exit 1
fi

echo "Current release: $CURRENT_TARGET"
echo "Rollback target: $TARGET"
echo "Database migrations will not be reversed. Confirm schema compatibility before proceeding."

if [[ "$ASSUME_YES" -ne 1 ]]; then
    if [[ ! -t 0 ]]; then
        echo "Use --yes for a non-interactive rollback." >&2
        exit 2
    fi
    read -r -p "Type ROLLBACK to continue: " CONFIRMATION
    if [[ "$CONFIRMATION" != "ROLLBACK" ]]; then
        echo "Rollback cancelled."
        exit 1
    fi
fi

ln -s "$TARGET" "$CURRENT_LINK.next"
mv -Tf "$CURRENT_LINK.next" "$CURRENT_LINK"
systemctl reload "$PHP_FPM_SERVICE"

if ! curl --fail --silent --show-error --max-time 10 "$HEALTHCHECK_URL" >/dev/null; then
    echo "Rollback target failed its health check; restoring the original release." >&2
    ln -s "$CURRENT_TARGET" "$CURRENT_LINK.next"
    mv -Tf "$CURRENT_LINK.next" "$CURRENT_LINK"
    systemctl reload "$PHP_FPM_SERVICE"
    exit 1
fi

echo "Rollback complete. Active release: $TARGET"
