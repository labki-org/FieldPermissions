#!/bin/bash
set -e

# Determine script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"

echo "==> Shutting down existing containers and removing volumes..."
docker compose down -v

echo "==> Starting new environment..."
docker compose up -d

echo "==> Waiting for MW to be ready..."
echo "==> Waiting for MW to be ready (giving DB time to init)..."
sleep 20

# This might not be needed
echo "==> Rebuilding LocalisationCache..."
docker compose exec wiki php maintenance/rebuildLocalisationCache.php --force

# echo "==> Populating Test Data..."
# if [ -f "$SCRIPT_DIR/../../dev/populate_test_data.sh" ]; then
#     # export MW_DIR="$REPO_ROOT" # Adjust if needed by populate script
#     # bash "$SCRIPT_DIR/../../dev/populate_test_data.sh"
# fi

echo "==> Environment ready!"
echo "Visit http://localhost:8888"
