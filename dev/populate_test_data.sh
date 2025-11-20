#!/usr/bin/env bash
set -euo pipefail

#
# FieldPermissions — Populate MediaWiki test environment with templates, forms, and pages
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

# Create or update a page using maintenance/edit.php
# Usage: create_page "Title" [summary] <<'EOF' ... EOF
# Note: When using heredoc, the summary must be passed before the heredoc
#       or we can call it as: create_page "Title" <<'EOF' ... EOF
#       and the summary will default
create_page() {
    local title="$1"
    local summary="Created by populate_test_data.sh"
    
    # If $2 exists and doesn't look like it's part of a heredoc, use it as summary
    # But when using heredoc, $2 will be empty, so we use default
    # For now, we'll just use the default summary since all calls use heredoc

    echo "  Creating page: $title"
    docker compose exec -T mediawiki php "$CONTAINER_WIKI/maintenance/edit.php" \
        --user "$MW_ADMIN_USER" \
        --summary "$summary" \
        --no-rc \
        "$title"
}

# Create a user and optionally add to groups
create_user() {
    local username="$1"
    local password="${2:-TestPass123!}"
    local groups="${3:-}"

    echo "  Creating user: $username"
    # Create the user first
    docker compose exec -T mediawiki php "$CONTAINER_WIKI/maintenance/createAndPromote.php" \
        --force \
        "$username" \
        "$password"
    
    # Add groups separately if specified using a PHP script
    if [ -n "$groups" ]; then
        echo "  Adding user $username to groups: $groups"
        # Split comma-separated groups and add each one
        IFS=',' read -ra GROUP_ARRAY <<< "$groups"
        for group in "${GROUP_ARRAY[@]}"; do
            group=$(echo "$group" | xargs) # trim whitespace
            if [ -n "$group" ]; then
                echo "    Adding to group: $group"
                # Copy the maintenance script from dev/ to the extension's maintenance directory
                docker compose cp "$SCRIPT_DIR/addUserToGroup.php" mediawiki:"$CONTAINER_WIKI/extensions/FieldPermissions/maintenance/addUserToGroup.php" 2>/dev/null || true
                # Use the FieldPermissions maintenance script
                docker compose exec -T mediawiki php \
                    "$CONTAINER_WIKI/extensions/FieldPermissions/maintenance/addUserToGroup.php" \
                    "$username" "$group" 2>&1 | grep -v "WARN" || echo "    Warning: Could not add group $group"
            fi
        done
    fi
}

# ---------------- MAIN ----------------

echo "==> Populating MediaWiki test environment with templates, forms, and pages"
echo "==> Using MW directory: $MW_DIR"

if [ ! -d "$MW_DIR" ]; then
    echo "ERROR: MediaWiki directory not found. Please run setup_mw_test_env.sh first."
    exit 1
fi

cd "$MW_DIR"

# Check if containers are running
if ! docker compose ps | grep -q "mediawiki.*Up"; then
    echo "ERROR: MediaWiki containers are not running. Please start them first."
    exit 1
fi

echo ""
echo "==> Creating templates..."

# Template:Person - Demonstrates all three permission levels
create_page "Template:Person" <<'TEMPLATE_EOF'
{| class="wikitable"
! Name
| {{{name|}}}
|-
! Title
| {{{title|}}}
|-
! Email
| {{#field:internal|{{{email|}}}}}
|-
! Phone
| {{#field:internal|{{{phone|}}}}}
|-
! Salary
| {{#field:sensitive|{{{salary|}}}}}
|-
! Home Address
| {{#field:sensitive|{{{home_address|}}}}}
|}
TEMPLATE_EOF

# Template:Project - Demonstrates group-based permissions
create_page "Template:Project" <<'TEMPLATE_EOF'
{| class="wikitable"
! Project Name
| {{{project_name|}}}
|-
! Description
| {{{description|}}}
|-
! Status
| {{{status|}}}
|-
! Admin Notes
| {{#field-groups:sysop,pi|{{{admin_notes|}}}}}
|-
! Internal Notes
| {{#field-groups:lab_member,pi|{{{internal_notes|}}}}}
|}
TEMPLATE_EOF

# Template:Equipment - Demonstrates SMW property protection
create_page "Template:Equipment" <<'TEMPLATE_EOF'
{| class="wikitable"
! Equipment Name
| {{{equipment_name|}}}
|-
! Model
| {{{model|}}}
|-
! Serial Number
| {{#field:internal|[[Serial Number::{{{serial_number|}}}]]}}
|-
! Purchase Price
| {{#field:sensitive|[[Purchase Price::{{{purchase_price|}}}]]}}
|-
! Location
| {{{location|}}}
|}
TEMPLATE_EOF

echo ""
echo "==> Creating SMW property pages..."

# SMW Property:Email
create_page "Property:Email" <<'PROPERTY_EOF'
[[Has type::Email]]
This property stores email addresses.
PROPERTY_EOF

# SMW Property:Salary
create_page "Property:Salary" <<'PROPERTY_EOF'
[[Has type::Number]]
This property stores salary information.
PROPERTY_EOF

# SMW Property:Serial Number
create_page "Property:Serial Number" <<'PROPERTY_EOF'
[[Has type::Text]]
This property stores equipment serial numbers.
PROPERTY_EOF

# SMW Property:Purchase Price
create_page "Property:Purchase Price" <<'PROPERTY_EOF'
[[Has type::Number]]
This property stores purchase prices for equipment.
PROPERTY_EOF

echo ""
echo "==> Creating sample pages using templates..."

# Person pages
create_page "Person:John Doe" <<'PAGE_EOF'
{{Person
|name=John Doe
|title=Senior Researcher
|email=john.doe@example.com
|phone=+1-555-0101
|salary=$95,000
|home_address=123 Main St, Anytown, USA
}}
PAGE_EOF

create_page "Person:Jane Smith" <<'PAGE_EOF'
{{Person
|name=Jane Smith
|title=Lab Manager
|email=jane.smith@example.com
|phone=+1-555-0102
|salary=$120,000
|home_address=456 Oak Ave, Anytown, USA
}}
PAGE_EOF

create_page "Person:Bob Johnson" <<'PAGE_EOF'
{{Person
|name=Bob Johnson
|title=Research Assistant
|email=bob.johnson@example.com
|phone=+1-555-0103
|salary=$65,000
|home_address=789 Pine Rd, Anytown, USA
}}
PAGE_EOF

# Project pages
create_page "Project:Alpha Research" <<'PAGE_EOF'
{{Project
|project_name=Alpha Research
|description=Investigation into alpha particles and their properties
|status=Active
|admin_notes=High priority project. Requires quarterly reviews.
|internal_notes=Team meeting scheduled for next week.
}}
PAGE_EOF

create_page "Project:Beta Analysis" <<'PAGE_EOF'
{{Project
|project_name=Beta Analysis
|description=Statistical analysis of beta distribution patterns
|status=Completed
|admin_notes=Project completed successfully. Final report pending.
|internal_notes=Data archived in secure repository.
}}
PAGE_EOF

# Equipment pages
create_page "Equipment:Microscope-001" <<'PAGE_EOF'
{{Equipment
|equipment_name=High-Resolution Microscope
|model=Zeiss Axio Observer 7
|serial_number=ZM-2024-001
|purchase_price=$45,000
|location=Lab Room 101
}}
PAGE_EOF

create_page "Equipment:Spectrometer-002" <<'PAGE_EOF'
{{Equipment
|equipment_name=Mass Spectrometer
|model=Thermo Scientific Q Exactive
|serial_number=MS-2024-002
|purchase_price=$125,000
|location=Lab Room 203
}}
PAGE_EOF

echo ""
echo "==> Creating semantic pages with SMW properties..."

# Create a page that directly uses SMW properties with field permissions
create_page "Employee:Alice Williams" <<'PAGE_EOF'
== Personal Information ==
* Name: Alice Williams
* Title: Principal Investigator

== Contact Information ==
{{#field:internal|* Email: [[Email::alice.williams@example.com]]}}

== Compensation ==
{{#field:sensitive|* Salary: [[Salary::150000]]}}
PAGE_EOF

create_page "Employee:Charlie Brown" <<'PAGE_EOF'
== Personal Information ==
* Name: Charlie Brown
* Title: Lab Member

== Contact Information ==
{{#field:internal|* Email: [[Email::charlie.brown@example.com]]}}

== Compensation ==
{{#field:sensitive|* Salary: [[Salary::75000]]}}
PAGE_EOF

echo ""
echo "==> Creating SMW query page for testing property filtering..."

create_page "Query:All Employees" <<'PAGE_EOF'
{{#ask:
 [[Category:Employee]]
 |?Email
 |?Salary
 |format=table
 |headers=plain
}}
PAGE_EOF

# Add category to employee pages
create_page "Category:Employee" <<'CATEGORY_EOF'
This category contains employee pages.
CATEGORY_EOF

# Update employee pages to include category
create_page "Employee:Alice Williams" <<'PAGE_EOF'
[[Category:Employee]]

== Personal Information ==
* Name: Alice Williams
* Title: Principal Investigator

== Contact Information ==
{{#field:internal|* Email: [[Email::alice.williams@example.com]]}}

== Compensation ==
{{#field:sensitive|* Salary: [[Salary::150000]]}}
PAGE_EOF

create_page "Employee:Charlie Brown" <<'PAGE_EOF'
[[Category:Employee]]

== Personal Information ==
* Name: Charlie Brown
* Title: Lab Member

== Contact Information ==
{{#field:internal|* Email: [[Email::charlie.brown@example.com]]}}

== Compensation ==
{{#field:sensitive|* Salary: [[Salary::75000]]}}
PAGE_EOF

echo ""
echo "==> Creating test users with different group memberships..."

# Create test users (optional - can be skipped if users already exist)
# Using stronger passwords to meet MediaWiki password requirements
create_user "TestUser" "TestPass123!" ""
create_user "LabMember" "TestPass123!" "lab_member"
create_user "PI" "TestPass123!" "pi"

echo ""
echo "==> Running SMW data update..."
docker compose exec -T mediawiki php "$CONTAINER_WIKI/extensions/SemanticMediaWiki/maintenance/rebuildData.php" || echo "  Note: SMW data rebuild may take time, continuing..."

echo ""
echo "DONE"
echo "Test data has been populated. You can now:"
echo "  - Visit http://localhost:$MW_PORT/w/index.php/Person:John_Doe"
echo "  - Visit http://localhost:$MW_PORT/w/index.php/Project:Alpha_Research"
echo "  - Visit http://localhost:$MW_PORT/w/index.php/Equipment:Microscope-001"
echo "  - Visit http://localhost:$MW_PORT/w/index.php/Query:All_Employees"
echo ""
echo "Test users created (password: TestPass123!):"
echo "  - TestUser (public access only)"
echo "  - LabMember (lab_member group - internal access)"
echo "  - PI (pi group - sensitive access)"
echo ""
echo "Log in as different users to test permission levels!"

