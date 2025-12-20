#!/usr/bin/env bash
set -euo pipefail

#
# Quick script to rebuild MediaWiki caches after extension changes
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

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CACHE_BASE="$(get_cache_dir)"
MW_DIR="${MW_DIR:-$CACHE_BASE/mediawiki-FieldPermissions-test}"
CONTAINER_WIKI="/var/www/html/w"

if [ ! -d "$MW_DIR" ]; then
    echo "ERROR: MediaWiki directory not found. Please run setup_mw_test_env.sh first."
    exit 1
fi

cd "$MW_DIR"

if ! docker compose ps | grep -q "mediawiki.*Up"; then
    echo "ERROR: MediaWiki containers are not running."
    exit 1
fi

echo "==> Rebuilding localization cache..."
docker compose exec -T mediawiki php "$CONTAINER_WIKI/maintenance/rebuildLocalisationCache.php" --force

echo "==> Clearing parser cache..."
docker compose exec -T mediawiki php "$CONTAINER_WIKI/maintenance/rebuildall.php" --quick || true

echo ""
echo "DONE - Caches rebuilt. Try accessing your pages again."

