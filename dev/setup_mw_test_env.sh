#!/usr/bin/env bash
set -euo pipefail

#
# FieldPermissions — MediaWiki test environment setup script (SQLite)
#

get_cache_dir() {
    case "$(uname -s)" in
        Darwin*) echo "$HOME/Library/Caches/fieldpermissions" ;;
        MINGW*|MSYS*|CYGWIN*)
            local appdata="${LOCALAPPDATA:-$HOME/AppData/Local}"
            echo "$appdata/fieldpermissions"
            ;;
        *) echo "${XDG_CACHE_HOME:-$HOME/.cache}/fieldpermissions" ;;
    esac
}

# ---------------- CONFIG ----------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CACHE_BASE="$(get_cache_dir)"
MW_DIR="${MW_DIR:-$CACHE_BASE/mediawiki-FieldPermissions-test}"
EXT_DIR="${EXT_DIR:-$SCRIPT_DIR/..}"
MW_BRANCH=REL1_44
MW_PORT=8888
MW_ADMIN_USER=Admin
MW_ADMIN_PASS=dockerpass

CONTAINER_WIKI="/var/www/html/w"
CONTAINER_LOG_DIR="/var/log/fieldpermissions"
CONTAINER_LOG_FILE="$CONTAINER_LOG_DIR/fieldpermissions.log"
LOG_DIR="$EXT_DIR/logs"

echo "==> Using MW directory: $MW_DIR"

# ---------------- RESET ENV ----------------

if [ -d "$MW_DIR" ]; then
    cd "$MW_DIR"
    docker compose down -v || true
fi

echo "==> Ensuring MediaWiki core exists..."
if [ ! -d "$MW_DIR/.git" ]; then
    mkdir -p "$(dirname "$MW_DIR")"
    git clone https://gerrit.wikimedia.org/r/mediawiki/core.git "$MW_DIR"
fi

cd "$MW_DIR"

git fetch --all
git checkout "$MW_BRANCH"
git reset --hard "$MW_BRANCH"
git clean -fdx
git submodule update --init --recursive || true

# ---------------- DOCKER ENV ----------------

cat > "$MW_DIR/.env" <<EOF
MW_SCRIPT_PATH=/w
MW_SERVER=http://localhost:$MW_PORT
MW_DOCKER_PORT=$MW_PORT
MEDIAWIKI_USER=$MW_ADMIN_USER
MEDIAWIKI_PASSWORD=$MW_ADMIN_PASS
MW_DOCKER_UID=$(id -u)
MW_DOCKER_GID=$(id -g)
EOF

echo "==> Starting MW containers..."
docker compose up -d

echo "==> Installing composer deps (core only)..."
docker compose exec -T mediawiki composer update --no-interaction --no-progress

echo "==> Running MediaWiki install script..."
# IMPORTANT: LocalSettings.php must *not* reference SemanticMediaWiki yet
docker compose exec -T mediawiki bash -lc "rm -f $CONTAINER_WIKI/LocalSettings.php"
docker compose exec -T mediawiki /bin/bash /docker/install.sh

echo "==> Fixing SQLite permissions..."
docker compose exec -T mediawiki bash -lc "chmod -R o+rwx $CONTAINER_WIKI/cache/sqlite"

# ---------------- EXTENSION & LOG MOUNTS ----------------

echo "==> Preparing host log directory..."
mkdir -p "$LOG_DIR"
chmod 777 "$LOG_DIR"

echo "==> Writing override file..."
cat > "$MW_DIR/docker-compose.override.yml" <<EOF
services:
  mediawiki:
    user: "$(id -u):$(id -g)"
    volumes:
      - $EXT_DIR:/var/www/html/w/extensions/FieldPermissions:cached
      - $LOG_DIR:$CONTAINER_LOG_DIR
EOF

echo "==> Restarting with extension mount..."
docker compose down
docker compose up -d

# ---------------- INSTALL SEMANTIC MEDIAWIKI ----------------

echo "==> Installing SMW via composer..."
docker compose exec -T mediawiki bash -lc "
  cd $CONTAINER_WIKI
  composer require mediawiki/semantic-media-wiki:'~6.0' --no-progress
"

echo "==> Enabling SMW..."
docker compose exec -T mediawiki bash -lc "
  sed -i '/SemanticMediaWiki/d' $CONTAINER_WIKI/LocalSettings.php
  {
    echo ''
    echo '// === Semantic MediaWiki ==='
    echo 'wfLoadExtension( \"SemanticMediaWiki\" );'
    echo 'enableSemantics( \"localhost\" );'
  } >> $CONTAINER_WIKI/LocalSettings.php
"

echo "==> Running MW updater..."
docker compose exec -T mediawiki php maintenance/update.php --quick

echo "==> Initializing SMW store..."
docker compose exec -T mediawiki php extensions/SemanticMediaWiki/maintenance/setupStore.php --nochecks

# ---------------- FIELD PERMISSIONS ----------------

echo "==> Installing FieldPermissions settings..."
docker compose exec -T mediawiki bash -lc "
  sed -i '/FieldPermissions/d' $CONTAINER_WIKI/LocalSettings.php

  {
    echo ''
    echo '// === FieldPermissions ==='
    echo 'wfLoadExtension( \"FieldPermissions\" );'
    echo '\$wgDebugLogGroups[\"fieldpermissions\"] = \"$CONTAINER_LOG_FILE\";'
    echo ''
    echo '// Define custom user groups for FieldPermissions'
    echo '\$wgGroupPermissions[\"lab_member\"] = \$wgGroupPermissions[\"user\"];'
    echo '\$wgGroupPermissions[\"pi\"] = \$wgGroupPermissions[\"user\"];'
  } >> $CONTAINER_WIKI/LocalSettings.php
"

echo "==> Running MW updater for FieldPermissions schema..."
docker compose exec -T mediawiki php maintenance/update.php --quick

# ---------------- CACHE DIRECTORY ----------------

echo "==> Setting cache directory..."
docker compose exec -T mediawiki bash -lc "
  sed -i '/wgCacheDirectory/d' $CONTAINER_WIKI/LocalSettings.php
  sed -i '/\\$IP = __DIR__/a \$wgCacheDirectory = \"\$IP/cache-fieldpermissions\";' $CONTAINER_WIKI/LocalSettings.php
"

# ---------------- REBUILD L10N ----------------

echo "==> Rebuilding LocalisationCache..."
docker compose exec -T mediawiki php maintenance/rebuildLocalisationCache.php --force

# ---------------- TEST ----------------

echo "==> Testing FieldPermissions logging..."
docker compose exec -T mediawiki php -r "
define('MW_INSTALL_PATH','/var/www/html/w');
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once MW_INSTALL_PATH . '/includes/WebStart.php';
wfDebugLog('fieldpermissions', 'FP test at '.date('H:i:s'));
echo \"OK\n\";
"

docker compose exec -T mediawiki tail -n 5 "$CONTAINER_LOG_FILE"

# ---------------- POPULATE TEST DATA (OPTIONAL) ----------------

if [ "${POPULATE_TEST_DATA:-}" = "1" ]; then
    echo ""
    echo "==> Populating test data..."
    "$SCRIPT_DIR/populate_test_data.sh" || echo "  Warning: Failed to populate test data (this is optional)"
fi

echo "DONE"
echo "Visit: http://localhost:$MW_PORT/w"
echo "Logs at: $LOG_DIR"
if [ "${POPULATE_TEST_DATA:-}" != "1" ]; then
    echo ""
    echo "To populate test data, run:"
    echo "  POPULATE_TEST_DATA=1 $SCRIPT_DIR/setup_mw_test_env.sh"
    echo "  or"
    echo "  $SCRIPT_DIR/populate_test_data.sh"
fi
