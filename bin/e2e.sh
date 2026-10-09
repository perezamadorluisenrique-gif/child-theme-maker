#!/usr/bin/env bash
# Runs tests/e2e/smoke.mjs against a throwaway WordPress on SQLite, served by
# PHP's built-in server. Downloads come from GitHub only (no wordpress.org).
#
#   bin/e2e.sh                # WordPress 7.1.3
#   WP_VERSION=6.3 bin/e2e.sh # oldest supported
#
# Needs: php (pdo_sqlite, zip), git, curl, node, and Playwright with Chromium
# (set NODE_PATH to a global install, or run `npm i playwright` first).
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
WP_VERSION=${WP_VERSION:-7.1.3}
CACHE=${E2E_CACHE:-$ROOT/build/e2e-cache}
SITE=$ROOT/build/e2e-site
PORT=${E2E_PORT:-8899}
URL="http://localhost:$PORT"

mkdir -p "$CACHE"
[ -f "$CACHE/wp-cli.phar" ] || curl -sSfL -o "$CACHE/wp-cli.phar" https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar
[ -d "$CACHE/wp-$WP_VERSION" ] || git -c advice.detachedHead=false clone -q --depth 1 --branch "$WP_VERSION" https://github.com/WordPress/WordPress "$CACHE/wp-$WP_VERSION"
[ -d "$CACHE/sqlite" ] || git clone -q --depth 1 https://github.com/WordPress/sqlite-database-integration "$CACHE/sqlite"

bin/build.sh >/dev/null
rm -rf "$SITE" && mkdir -p "$SITE"
cp -r "$CACHE/wp-$WP_VERSION/." "$SITE/" && rm -rf "$SITE/.git"
SQLITE_SRC="$CACHE/sqlite/packages/plugin-sqlite-database-integration"
[ -d "$SQLITE_SRC" ] || SQLITE_SRC="$CACHE/sqlite"
cp -rL "$SQLITE_SRC" "$SITE/wp-content/plugins/sqlite-database-integration"
sed "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SITE/wp-content/plugins/sqlite-database-integration#" "$SITE/wp-content/plugins/sqlite-database-integration/db.copy" > "$SITE/wp-content/db.php"
cp -r build/child-theme-maker "$SITE/wp-content/plugins/child-theme-maker"
mkdir -p "$SITE/wp-content/database"
cat > "$SITE/wp-config.php" <<PHP
<?php
define( 'DB_NAME', 'wp' ); define( 'DB_USER', '' ); define( 'DB_PASSWORD', '' ); define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' ); define( 'DB_FILE', 'wp.sqlite' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as \$k ) { define( \$k, 'e2e-' . \$k ); }
\$table_prefix = 'wp_';
define( 'WP_HOME', '$URL' ); define( 'WP_SITEURL', '$URL' );
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', __DIR__ . '/debug.log' ); define( 'WP_DEBUG_DISPLAY', false );
define( 'FS_METHOD', 'direct' );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
PHP

WP="php $CACHE/wp-cli.phar --allow-root --path=$SITE"
$WP core install --url="$URL" --title=E2E --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email >/dev/null 2>&1
$WP plugin activate child-theme-maker >/dev/null 2>&1
echo "WordPress $($WP core version 2>/dev/null) on SQLite at $URL"

php -S "localhost:$PORT" -t "$SITE" >"$ROOT/build/e2e-server.log" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null' EXIT
for _ in $(seq 1 50); do curl -s -o /dev/null "$URL/wp-login.php" && break; sleep 0.2; done

WP_PATH="$SITE" WP_CLI="$WP" BASE_URL="$URL" node tests/e2e/smoke.mjs
