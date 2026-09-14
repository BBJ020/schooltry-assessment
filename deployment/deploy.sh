#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

RELEASE_ID="${1:-}"
ARCHIVE_PATH="${2:-}"
APP_ROOT="/var/www/schooltry"
PHP_FPM_SERVICE="php8.3-fpm"
DEPLOY_USER="schooltry"
WEB_USER="www-data"
WEB_GROUP="www-data"
RELEASES_TO_KEEP=5
HEALTHCHECK_URL="http://127.0.0.1/up"

if [[ ! "$RELEASE_ID" =~ ^[A-Za-z0-9][A-Za-z0-9._-]*$ ]]; then
    echo "A safe release ID is required." >&2
    exit 2
fi

if [[ ! -f "$ARCHIVE_PATH" ]]; then
    echo "Release archive not found: $ARCHIVE_PATH" >&2
    exit 2
fi

ARCHIVE_PATH="$(realpath -e "$ARCHIVE_PATH")"
if [[ ! "$ARCHIVE_PATH" =~ ^/tmp/schooltry-[0-9a-f]{40}\.tar\.gz$ ]]; then
    echo "The archive must use the expected /tmp/schooltry-<commit>.tar.gz path." >&2
    exit 2
fi

if ! runuser -u "$DEPLOY_USER" -- test -r "$ARCHIVE_PATH"; then
    echo "The schooltry deployment account cannot read the release archive." >&2
    exit 2
fi

RELEASES_DIR="$APP_ROOT/releases"
SHARED_DIR="$APP_ROOT/shared"
CURRENT_LINK="$APP_ROOT/current"
FINAL_DIR="$RELEASES_DIR/$RELEASE_ID"
STAGING_DIR="$RELEASES_DIR/.$RELEASE_ID.preparing"
LOCK_FILE="/var/lock/schooltry-deploy.lock"
PREVIOUS_TARGET=""
SWITCHED=0

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "Another SchoolTry deployment is already running." >&2
    exit 1
fi

cleanup() {
    if [[ -d "$STAGING_DIR" ]]; then
        rm -rf -- "$STAGING_DIR"
    fi
    rm -f -- "$ARCHIVE_PATH"
}
trap cleanup EXIT

install -d -m 0755 "$RELEASES_DIR" "$SHARED_DIR"
install -d -m 0770 \
    "$SHARED_DIR/storage/app/private" \
    "$SHARED_DIR/storage/app/public" \
    "$SHARED_DIR/storage/framework/cache" \
    "$SHARED_DIR/storage/framework/sessions" \
    "$SHARED_DIR/storage/framework/views" \
    "$SHARED_DIR/storage/logs"

if [[ ! -f "$SHARED_DIR/.env" ]]; then
    echo "Missing $SHARED_DIR/.env. Provision production settings before deploying." >&2
    exit 1
fi

if [[ -e "$FINAL_DIR" || -e "$STAGING_DIR" ]]; then
    echo "Release already exists: $RELEASE_ID" >&2
    exit 1
fi

if [[ -L "$CURRENT_LINK" ]]; then
    PREVIOUS_TARGET="$(readlink -f "$CURRENT_LINK")"
fi

install -d -m 0755 "$STAGING_DIR"
chown "$DEPLOY_USER:$WEB_GROUP" "$STAGING_DIR"
runuser -u "$DEPLOY_USER" -- tar -xzf "$ARCHIVE_PATH" -C "$STAGING_DIR"

rm -rf -- "$STAGING_DIR/storage"
ln -s "$SHARED_DIR/storage" "$STAGING_DIR/storage"
ln -s "$SHARED_DIR/.env" "$STAGING_DIR/.env"
install -d -m 0770 "$STAGING_DIR/bootstrap/cache"
chown -R "$DEPLOY_USER:$WEB_GROUP" "$STAGING_DIR" "$SHARED_DIR/storage"

cd "$STAGING_DIR"
runuser -u "$DEPLOY_USER" -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
runuser -u "$DEPLOY_USER" -- npm ci
runuser -u "$DEPLOY_USER" -- npm run build

runuser -u "$DEPLOY_USER" -- php artisan config:clear
runuser -u "$DEPLOY_USER" -- php artisan config:cache
runuser -u "$DEPLOY_USER" -- php artisan route:cache
runuser -u "$DEPLOY_USER" -- php artisan view:cache
runuser -u "$DEPLOY_USER" -- php artisan migrate --force

chown -R "$WEB_USER:$WEB_GROUP" "$SHARED_DIR/storage" "$STAGING_DIR/bootstrap/cache"
chmod -R u=rwX,g=rwX,o= "$SHARED_DIR/storage" "$STAGING_DIR/bootstrap/cache"

mv -- "$STAGING_DIR" "$FINAL_DIR"
ln -s "$FINAL_DIR" "$CURRENT_LINK.next"
mv -Tf "$CURRENT_LINK.next" "$CURRENT_LINK"
SWITCHED=1

systemctl reload "$PHP_FPM_SERVICE"

if ! curl --fail --silent --show-error --max-time 10 "$HEALTHCHECK_URL" >/dev/null; then
    echo "Health check failed after activation; restoring the prior application release." >&2

    if [[ -n "$PREVIOUS_TARGET" && -d "$PREVIOUS_TARGET" ]]; then
        ln -s "$PREVIOUS_TARGET" "$CURRENT_LINK.next"
        mv -Tf "$CURRENT_LINK.next" "$CURRENT_LINK"
    elif [[ "$SWITCHED" -eq 1 ]]; then
        unlink "$CURRENT_LINK"
    fi

    systemctl reload "$PHP_FPM_SERVICE"
    exit 1
fi

mapfile -t OLD_RELEASES < <(
    find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d ! -name '.*.preparing' -printf '%T@ %p\n' \
        | sort -nr \
        | cut -d' ' -f2-
)

for ((index = RELEASES_TO_KEEP; index < ${#OLD_RELEASES[@]}; index++)); do
    OLD_RELEASE="$(realpath -m "${OLD_RELEASES[$index]}")"
    if [[ "$OLD_RELEASE" == "$RELEASES_DIR/"* && "$OLD_RELEASE" != "$(readlink -f "$CURRENT_LINK")" ]]; then
        rm -rf -- "$OLD_RELEASE"
    fi
done

echo "SchoolTry release $RELEASE_ID is active."
