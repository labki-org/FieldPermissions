#!/usr/bin/env bash
set -euo pipefail

#
# FieldPermissions — Populate MediaWiki test environment
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
CONTAINER_WIKI="/var/www/html/w"
MW_ADMIN_USER="${MW_ADMIN_USER:-Admin}"
MW_PORT="${MW_PORT:-8888}"

# ---------------- HELPER FUNCTIONS ----------------

create_page() {
    local title="$1"
    local summary="Created by populate_test_data.sh"
    
    echo "  Creating page: $title"
    docker compose exec -T mediawiki php "$CONTAINER_WIKI/maintenance/edit.php" \
        --user "$MW_ADMIN_USER" \
        --summary "$summary" \
        --no-rc \
        "$title"
}

create_user() {
    local username="$1"
    local password="${2:-TestPass123!}"
    local groups="${3:-}"

    echo "  Creating user: $username"
    docker compose exec -T mediawiki php "$CONTAINER_WIKI/maintenance/createAndPromote.php" \
        --force \
        "$username" \
        "$password"
    
    if [ -n "$groups" ]; then
        echo "  Adding user $username to groups: $groups"
        IFS=',' read -ra GROUP_ARRAY <<< "$groups"
        for group in "${GROUP_ARRAY[@]}"; do
            group=$(echo "$group" | xargs)
            if [ -n "$group" ]; then
                 # We can use core maintenance script if available or simple sql
                 # MW has maintenance/userOptions.php but adding to group is simpler via createAndPromote if we run it again?
                 # createAndPromote --custom-groups
                 docker compose exec -T mediawiki php "$CONTAINER_WIKI/maintenance/createAndPromote.php" \
                    --force \
                    --custom-groups "$group" \
                    "$username" "$password"
            fi
        done
    fi
}

# ---------------- MAIN ----------------

echo "==> Populating MediaWiki test environment"

if [ ! -d "$MW_DIR" ]; then
    echo "ERROR: MediaWiki directory not found. Please run setup_mw_test_env.sh first."
    exit 1
fi

cd "$MW_DIR"

if ! docker compose ps | grep -q "mediawiki.*Up"; then
    echo "ERROR: MediaWiki containers are not running. Please start them first."
    exit 1
fi

# 1. Seed Database Tables
echo ""
echo "==> Seeding FieldPermissions database tables..."
docker compose cp "$SCRIPT_DIR/seed_db.php" mediawiki:"$CONTAINER_WIKI/extensions/FieldPermissions/maintenance/seed_db.php"
docker compose exec -T mediawiki php "$CONTAINER_WIKI/extensions/FieldPermissions/maintenance/seed_db.php"

echo ""
echo "==> Creating Metadata Properties..."

# Property:Has visibility level
create_page "Property:Has visibility level" <<'EOF'
[[Has type::Page]]
This property defines the visibility level of a property or page.
EOF

# Property:Visible to
create_page "Property:Visible to" <<'EOF'
[[Has type::Text]]
[[Has type::Page]]
This property defines which groups can see a property.
EOF

# Property:Has numeric visibility value (for Visibility pages)
create_page "Property:Has numeric visibility value" <<'EOF'
[[Has type::Number]]
EOF

echo ""
echo "==> Creating Visibility Levels Pages..."
# (Optional, as DB has the mapping, but nice for SMW linkage)

create_page "Visibility:Public" <<'EOF'
[[Has numeric visibility value::0]]
Public visibility.
EOF

create_page "Visibility:Internal" <<'EOF'
[[Has numeric visibility value::10]]
Internal visibility (Lab Members).
EOF

create_page "Visibility:Private" <<'EOF'
[[Has numeric visibility value::20]]
Private visibility (PIs).
EOF

create_page "Visibility:PI_Only" <<'EOF'
[[Has numeric visibility value::30]]
Strictly PI Only.
EOF

echo ""
echo "==> Creating Data Properties..."

# Property:Email - Internal
create_page "Property:Email" <<'EOF'
[[Has type::Email]]
[[Has visibility level::Visibility:Internal]]
EOF

# Property:Salary - Private
create_page "Property:Salary" <<'EOF'
[[Has type::Number]]
[[Has visibility level::Visibility:Private]]
EOF

# Property:SSN - PI Only
create_page "Property:SSN" <<'EOF'
[[Has type::Text]]
[[Has visibility level::Visibility:PI_Only]]
EOF

# Property:Notes - Visible to Admins only (Group based)
create_page "Property:Admin Notes" <<'EOF'
[[Has type::Text]]
[[Visible to::sysop]]
[[Visible to::pi]]
EOF

echo ""
echo "==> Creating Content Pages..."

create_page "Employee:Alice" <<'EOF'
[[Category:Employee]]
* Name: Alice
* Email: [[Email::alice@example.com]]
* Salary: [[Salary::50000]]
* SSN: [[SSN::123-45-6789]]
* Notes: [[Admin Notes::Great performance]]
EOF

create_page "Employee:Bob" <<'EOF'
[[Category:Employee]]
* Name: Bob
* Email: [[Email::bob@example.com]]
* Salary: [[Salary::60000]]
* SSN: [[SSN::987-65-4321]]
* Notes: [[Admin Notes::Needs review]]
EOF

echo ""
echo "==> Creating Query Pages..."

create_page "Query:Employees" <<'EOF'
{{#ask: [[Category:Employee]]
 |?Email
 |?Salary
 |?SSN
 |?Admin Notes
 |format=table
}}
EOF

echo ""
echo "==> Creating Users..."

create_user "PublicUser" "TestPass123!" ""
create_user "LabMember" "TestPass123!" "lab_member"
create_user "PI" "TestPass123!" "pi"

echo ""
echo "==> Rebuilding SMW Data..."
docker compose exec -T mediawiki php "$CONTAINER_WIKI/extensions/SemanticMediaWiki/maintenance/rebuildData.php" || echo "SMW rebuild warning (can be ignored if first run)"

echo ""
echo "DONE"
echo "Test data populated."
echo ""
echo "Testing Guide:"
echo "1. Log in as different users to test permissions:"
echo "   - PublicUser (No special groups) -> Should see only Public data"
echo "   - LabMember (Group: lab_member) -> Should see Public + Internal (Email)"
echo "   - PI (Group: pi) -> Should see Public + Internal + Private (Salary, Admin Notes) + PI Only (SSN)"
echo ""
echo "2. Key Pages to Visit:"
echo "   - http://localhost:$MW_PORT/w/index.php/Query:Employees (Main Test Query)"
echo "   - http://localhost:$MW_PORT/w/index.php/Employee:Alice (Individual Page)"
echo ""
echo "3. Configuration:"
echo "   - Manage Levels/Groups: http://localhost:$MW_PORT/w/index.php/Special:ManageVisibility"
