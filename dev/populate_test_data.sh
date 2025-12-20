#!/usr/bin/env bash
set -euo pipefail

#
# PropertyPermissions — Comprehensive Test Data Population Script
# Creates a realistic test environment with varied properties, employees, and queries
#

# ---------------- CONFIG ----------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# For labki-platform, we assume we run from the repo root or can find docker-compose.yml there
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CONTAINER_WIKI="/var/www/html"
MW_ADMIN_USER="${MW_ADMIN_USER:-Admin}"
MW_PORT="${MW_PORT:-8888}"
COMMON_PASSWORD="TestPass123!"

# ---------------- HELPER FUNCTIONS ----------------

create_page() {
    local title="$1"
    local summary="Created by populate_test_data.sh"
    
    echo "  Creating page: $title"
    docker compose exec -T wiki php "$CONTAINER_WIKI/maintenance/edit.php" \
        --user "$MW_ADMIN_USER" \
        --summary "$summary" \
        --no-rc \
        "$title"
}

create_user() {
    local username="$1"
    local password="${2:-$COMMON_PASSWORD}"
    local groups="${3:-}"

    echo "  Creating user: $username"
    docker compose exec -T wiki php "$CONTAINER_WIKI/maintenance/createAndPromote.php" \
        --force \
        "$username" \
        "$password"
    
    if [ -n "$groups" ]; then
        echo "  Adding user $username to groups: $groups"
        IFS=',' read -ra GROUP_ARRAY <<< "$groups"
        for group in "${GROUP_ARRAY[@]}"; do
            group=$(echo "$group" | xargs)
            if [ -n "$group" ]; then
                docker compose exec -T wiki php "$CONTAINER_WIKI/maintenance/createAndPromote.php" \
                    --force \
                    --custom-groups "$group" \
                    "$username" "$password"
            fi
        done
    fi
}

# ---------------- MAIN ----------------

echo "=========================================="
echo "PropertyPermissions Test Data Population"
echo "=========================================="
echo ""

cd "$REPO_ROOT"

if ! docker compose ps --services --filter "status=running" | grep -q "wiki"; then
    echo "ERROR: 'wiki' container is not running. Please start it with 'docker compose up -d'."
    exit 1
fi

# ============================================
# 1. SEED DATABASE TABLES
# ============================================
echo ""
echo "==> [1/7] Seeding PropertyPermissions database tables..."
# In labki-platform, the extension is mounted at /mw-user-extensions/PropertyPermissions
# Since it is a volume mount, we don't need to copy files.
docker compose exec -T wiki env MW_INSTALL_PATH="$CONTAINER_WIKI" php "/mw-user-extensions/PropertyPermissions/maintenance/seed_db.php"

# ============================================
# 2. CREATE METADATA PROPERTIES
# ============================================
echo ""
echo "==> [2/7] Creating Metadata Properties..."

create_page "Property:Has visibility level" <<'EOF'
[[Has type::Page]]
This property defines the visibility level of a property or page.
EOF

create_page "Property:Visible to" <<'EOF'
[[Has type::Text]]
[[Has type::Page]]
This property defines which groups can see a property.
EOF

create_page "Property:Has numeric visibility value" <<'EOF'
[[Has type::Number]]
Numeric value for visibility levels (0=Public, 10=Internal, 20=Private, 30=PI Only).
EOF

# ============================================
# 3. CREATE VISIBILITY LEVEL PAGES
# ============================================
echo ""
echo "==> [3/7] Creating Visibility Level Pages..."

create_page "Visibility:Public" <<'EOF'
[[Has numeric visibility value::0]]
Public visibility - visible to everyone, including anonymous users.
EOF

create_page "Visibility:Internal" <<'EOF'
[[Has numeric visibility value::10]]
Internal visibility - visible to lab members and above.
EOF

create_page "Visibility:Private" <<'EOF'
[[Has numeric visibility value::20]]
Private visibility - visible to PIs and administrators only.
EOF

create_page "Visibility:PI_Only" <<'EOF'
[[Has numeric visibility value::30]]
PI Only visibility - strictly for Principal Investigators and administrators.
EOF

# ============================================
# 4. CREATE DATA PROPERTIES WITH SENSIBLE VISIBILITY
# ============================================
echo ""
echo "==> [4/7] Creating Data Properties with Visibility Levels..."

# PUBLIC PROPERTIES (Level 0) - Basic organizational info everyone should see
create_page "Property:Name" <<'EOF'
[[Has type::Text]]
[[Has visibility level::Visibility:Public]]
Full name of the person.
EOF

create_page "Property:Department" <<'EOF'
[[Has type::Text]]
[[Has visibility level::Visibility:Public]]
Department or division the person belongs to.
EOF

create_page "Property:Office Location" <<'EOF'
[[Has type::Text]]
[[Has visibility level::Visibility:Public]]
Physical office location or building.
EOF

# INTERNAL PROPERTIES (Level 10) - Contact and employment info for lab members
create_page "Property:Email" <<'EOF'
[[Has type::Email]]
[[Has visibility level::Visibility:Internal]]
Email address for internal communication.
EOF

create_page "Property:Phone" <<'EOF'
[[Has type::Text]]
[[Has visibility level::Visibility:Internal]]
Phone number for internal contact.
EOF

create_page "Property:Hire Date" <<'EOF'
[[Has type::Date]]
[[Has visibility level::Visibility:Internal]]
Date when the person was hired.
EOF

# PRIVATE PROPERTIES (Level 20) - Sensitive HR and financial info for PIs
create_page "Property:Salary" <<'EOF'
[[Has type::Number]]
[[Has visibility level::Visibility:Private]]
Annual salary - sensitive financial information.
EOF

create_page "Property:Performance Rating" <<'EOF'
[[Has type::Number]]
[[Has visibility level::Visibility:Private]]
Performance rating (1-5 scale) - sensitive HR information.
EOF

create_page "Property:Emergency Contact" <<'EOF'
[[Has type::Text]]
[[Has visibility level::Visibility:Private]]
Emergency contact name and phone number.
EOF

# PI ONLY PROPERTIES (Level 30) - Highly sensitive information
create_page "Property:SSN" <<'EOF'
[[Has type::Text]]
[[Has visibility level::Visibility:PI_Only]]
Social Security Number - highly sensitive personal identifier.
EOF

create_page "Property:Background Check Status" <<'EOF'
[[Has type::Text]]
[[Has visibility level::Visibility:PI_Only]]
Background check clearance status.
EOF

# GROUP-BASED PROPERTIES - Visible to specific groups regardless of level
create_page "Property:Admin Notes" <<'EOF'
[[Has type::Text]]
[[Visible to::sysop]]
[[Visible to::pi]]
Administrative notes visible only to administrators and PIs.
EOF

create_page "Property:Security Clearance" <<'EOF'
[[Has type::Text]]
[[Visible to::security]]
[[Visible to::sysop]]
Security clearance level - visible only to security personnel and administrators.
EOF

# ============================================
# 5. CREATE CONTENT PAGES (EMPLOYEES)
# ============================================
echo ""
echo "==> [5/7] Creating Employee Pages..."

create_page "Employee:Alice Johnson" <<'EOF'
[[Category:Employee]]
[[Category:Research Staff]]

* Name: [[Name::Alice Johnson]]
* Department: [[Department::Research]]
* Office Location: [[Office Location::Building A, Room 201]]
* Email: [[Email::alice.johnson@example.com]]
* Phone: [[Phone::555-0101]]
* Hire Date: [[Hire Date::2020-01-15]]
* Salary: [[Salary::75000]]
* Performance Rating: [[Performance Rating::4]]
* Emergency Contact: [[Emergency Contact::John Johnson - 555-0102]]
* SSN: [[SSN::123-45-6789]]
* Background Check Status: [[Background Check Status::Cleared]]
* Admin Notes: [[Admin Notes::Excellent researcher, potential for promotion]]
* Security Clearance: [[Security Clearance::Level 2]]
EOF

create_page "Employee:Bob Smith" <<'EOF'
[[Category:Employee]]
[[Category:Research Staff]]

* Name: [[Name::Bob Smith]]
* Department: [[Department::Research]]
* Office Location: [[Office Location::Building A, Room 202]]
* Email: [[Email::bob.smith@example.com]]
* Phone: [[Phone::555-0103]]
* Hire Date: [[Hire Date::2019-06-01]]
* Salary: [[Salary::82000]]
* Performance Rating: [[Performance Rating::5]]
* Emergency Contact: [[Emergency Contact::Jane Smith - 555-0104]]
* SSN: [[SSN::987-65-4321]]
* Background Check Status: [[Background Check Status::Cleared]]
* Admin Notes: [[Admin Notes::Team lead candidate]]
* Security Clearance: [[Security Clearance::Level 3]]
EOF

create_page "Employee:Carol Williams" <<'EOF'
[[Category:Employee]]
[[Category:Administrative Staff]]

* Name: [[Name::Carol Williams]]
* Department: [[Department::Administration]]
* Office Location: [[Office Location::Building B, Room 101]]
* Email: [[Email::carol.williams@example.com]]
* Phone: [[Phone::555-0105]]
* Hire Date: [[Hire Date::2021-03-10]]
* Salary: [[Salary::65000]]
* Performance Rating: [[Performance Rating::4]]
* Emergency Contact: [[Emergency Contact::Mike Williams - 555-0106]]
* SSN: [[SSN::456-78-9012]]
* Background Check Status: [[Background Check Status::Pending]]
* Admin Notes: [[Admin Notes::New hire, still in training]]
* Security Clearance: [[Security Clearance::Level 1]]
EOF

create_page "Employee:David Brown" <<'EOF'
[[Category:Employee]]
[[Category:Research Staff]]

* Name: [[Name::David Brown]]
* Department: [[Department::Research]]
* Office Location: [[Office Location::Building A, Room 203]]
* Email: [[Email::david.brown@example.com]]
* Phone: [[Phone::555-0107]]
* Hire Date: [[Hire Date::2018-09-15]]
* Salary: [[Salary::95000]]
* Performance Rating: [[Performance Rating::3]]
* Emergency Contact: [[Emergency Contact::Sarah Brown - 555-0108]]
* SSN: [[SSN::789-01-2345]]
* Background Check Status: [[Background Check Status::Cleared]]
* Admin Notes: [[Admin Notes::Performance review scheduled]]
* Security Clearance: [[Security Clearance::Level 2]]
EOF

create_page "Employee:Emma Davis" <<'EOF'
[[Category:Employee]]
[[Category:PI]]

* Name: [[Name::Emma Davis]]
* Department: [[Department::Research]]
* Office Location: [[Office Location::Building A, Room 301]]
* Email: [[Email::emma.davis@example.com]]
* Phone: [[Phone::555-0109]]
* Hire Date: [[Hire Date::2015-01-05]]
* Salary: [[Salary::120000]]
* Performance Rating: [[Performance Rating::5]]
* Emergency Contact: [[Emergency Contact::Tom Davis - 555-0110]]
* SSN: [[SSN::321-54-9876]]
* Background Check Status: [[Background Check Status::Cleared]]
* Admin Notes: [[Admin Notes::Principal Investigator, grant recipient]]
* Security Clearance: [[Security Clearance::Level 4]]
EOF

# ============================================
# 6. CREATE QUERY PAGES (DIFFERENT FORMATS)
# ============================================
echo ""
echo "==> [6/7] Creating Query Pages with Different Formats..."

# Table format - main test query
create_page "Query:Employees" <<'EOF'
{{#ask: [[Category:Employee]]
 |?Name
 |?Department
 |?Office Location
 |?Email
 |?Phone
 |?Hire Date
 |?Salary
 |?Performance Rating
 |?Emergency Contact
 |?SSN
 |?Background Check Status
 |?Admin Notes
 |?Security Clearance
 |format=table
 |headers=show
 |sort=Name
}}
EOF

# List format
create_page "Query:Employees List" <<'EOF'
{{#ask: [[Category:Employee]]
 |?Name
 |?Department
 |?Email
 |?Phone
 |?Salary
 |?SSN
 |format=list
 |sort=Name
}}
EOF

# Template format (disabled - requires template class that may not exist in this SMW version)
# create_page "Query:Employees Template" <<'EOF'
# {{#ask: [[Category:Employee]]
#  |?Name
#  |?Department
#  |?Email
#  |?Salary
#  |?SSN
#  |format=template
#  |template=EmployeeCard
#  |sort=Name
# }}
# EOF

# JSON format
create_page "Query:Employees JSON" <<'EOF'
{{#ask: [[Category:Employee]]
 |?Name
 |?Department
 |?Email
 |?Salary
 |?SSN
 |format=json
 |sort=Name
}}
EOF

# CSV format
create_page "Query:Employees CSV" <<'EOF'
{{#ask: [[Category:Employee]]
 |?Name
 |?Department
 |?Email
 |?Salary
 |?SSN
 |format=csv
 |sort=Name
}}
EOF

# Broadtable format
create_page "Query:Employees Broadtable" <<'EOF'
{{#ask: [[Category:Employee]]
 |?Name
 |?Department
 |?Email
 |?Salary
 |?SSN
 |format=broadtable
 |sort=Name
}}
EOF

# Filtered query - only Research Staff
create_page "Query:Research Staff" <<'EOF'
{{#ask: [[Category:Employee]][[Category:Research Staff]]
 |?Name
 |?Department
 |?Email
 |?Phone
 |?Salary
 |?Performance Rating
 |?SSN
 |format=table
 |sort=Name
}}
EOF

# Minimal query - public fields only
create_page "Query:Public Directory" <<'EOF'
{{#ask: [[Category:Employee]]
 |?Name
 |?Department
 |?Office Location
 |format=table
 |sort=Name
}}
EOF

# ============================================
# 7. CREATE TEST USERS
# ============================================
echo ""
echo "==> [7/7] Creating Test Users..."

create_user "PublicUser" "$COMMON_PASSWORD" ""
create_user "LabMember" "$COMMON_PASSWORD" "lab_member"
create_user "PI" "$COMMON_PASSWORD" "pi"
create_user "SecurityOfficer" "$COMMON_PASSWORD" "security"
create_user "AdminUser" "$COMMON_PASSWORD" "sysop"

# ============================================
# 8. REBUILD SMW DATA
# ============================================
echo ""
echo "==> Rebuilding SMW Data..."
docker compose exec -T wiki php "$CONTAINER_WIKI/extensions/SemanticMediaWiki/maintenance/rebuildData.php" || echo "SMW rebuild warning (can be ignored if first run)"

# ============================================
# COMPREHENSIVE TESTING GUIDE
# ============================================
echo ""
echo "=========================================="
echo "✅ TEST DATA POPULATION COMPLETE"
echo "=========================================="
echo ""
echo "📋 TESTING GUIDE"
echo "=========================================="
echo ""
echo "🔐 LOGIN CREDENTIALS"
echo "   All test users use password: $COMMON_PASSWORD"
echo ""
echo "👥 TEST USERS & EXPECTED PERMISSIONS"
echo "   ┌─────────────────────┬─────────────────────────────────────────────────────────────┐"
echo "   │ User                 │ Visible Fields                                               │"
echo "   ├─────────────────────┼─────────────────────────────────────────────────────────────┤"
echo "   │ PublicUser           │ Name, Department, Office Location                           │"
echo "   │ (no groups)          │ (Public - Level 0)                                          │"
echo "   ├─────────────────────┼─────────────────────────────────────────────────────────────┤"
echo "   │ LabMember            │ + Email, Phone, Hire Date                                    │"
echo "   │ (lab_member)         │ (Public + Internal - Level 10)                             │"
echo "   ├─────────────────────┼─────────────────────────────────────────────────────────────┤"
echo "   │ PI                   │ + Salary, Performance Rating, Emergency Contact               │"
echo "   │ (pi)                 │ + SSN, Background Check Status                              │"
echo "   │                      │ + Admin Notes                                               │"
echo "   │                      │ (Public + Internal + Private + PI Only - Level 30)         │"
echo "   ├─────────────────────┼─────────────────────────────────────────────────────────────┤"
echo "   │ SecurityOfficer      │ + Security Clearance (group-based)                         │"
echo "   │ (security)           │ (Same as PublicUser + Security Clearance)                 │"
echo "   ├─────────────────────┼─────────────────────────────────────────────────────────────┤"
echo "   │ AdminUser            │ ALL fields (sysop group)                                    │"
echo "   │ (sysop)              │ (Full access)                                               │"
echo "   └─────────────────────┴─────────────────────────────────────────────────────────────┘"
echo ""
echo "📄 KEY PAGES TO TEST"
echo "   ┌──────────────────────────────────────────────────────────────────────────────────┐"
echo "   │ QUERY PAGES (Test different result formats)                                       │"
echo "   ├──────────────────────────────────────────────────────────────────────────────────┤"
echo "   │ • Query:Employees          - Main test query (table format)                       │"
echo "   │ • Query:Employees List     - List format                                         │"
echo "   │ • Query:Employees JSON     - JSON format                                         │"
echo "   │ • Query:Employees CSV      - CSV format                                          │"
echo "   │ • Query:Employees Broadtable - Broadtable format                                 │"
echo "   │ • Query:Research Staff     - Filtered query (Research Staff only)               │"
echo "   │ • Query:Public Directory   - Public fields only                                  │"
echo "   │                                                                                    │"
echo "   │ NOTE: Template format disabled (TemplateResultPrinter class not available)         │"
echo "   ├──────────────────────────────────────────────────────────────────────────────────┤"
echo "   │ INDIVIDUAL PAGES (Test factbox filtering)                                         │"
echo "   ├──────────────────────────────────────────────────────────────────────────────────┤"
echo "   │ • Employee:Alice Johnson   - Research Staff                                      │"
echo "   │ • Employee:Bob Smith        - Research Staff                                      │"
echo "   │ • Employee:Carol Williams  - Administrative Staff                                │"
echo "   │ • Employee:David Brown     - Research Staff                                      │"
echo "   │ • Employee:Emma Davis      - PI                                                  │"
echo "   └──────────────────────────────────────────────────────────────────────────────────┘"
echo ""
echo "🔗 URLS (Base: http://localhost:$MW_PORT/w/index.php)"
echo ""
echo "   QUERY PAGES:"
echo "   • /Query:Employees"
echo "   • /Query:Employees_List"
echo "   • /Query:Employees_JSON"
echo "   • /Query:Employees_CSV"
echo "   • /Query:Employees_Broadtable"
echo "   • /Query:Research_Staff"
echo "   • /Query:Public_Directory"
echo "   (Template format disabled - TemplateResultPrinter class not available)"
echo ""
echo "   INDIVIDUAL PAGES:"
echo "   • /Employee:Alice_Johnson"
echo "   • /Employee:Bob_Smith"
echo "   • /Employee:Carol_Williams"
echo "   • /Employee:David_Brown"
echo "   • /Employee:Emma_Davis"
echo ""
echo "   ADMINISTRATION:"
echo "   • /Special:ManageVisibility (Manage visibility levels and group mappings)"
echo ""
echo "🧪 TESTING CHECKLIST"
echo ""
echo "   □ 1. Test as PublicUser (logged out or PublicUser account)"
echo "      → Should see: Name, Department, Office Location only"
echo "      → Should NOT see: Email, Salary, SSN, Admin Notes, etc."
echo ""
echo "   □ 2. Test as LabMember"
echo "      → Should see: Name, Department, Office Location, Email, Phone, Hire Date"
echo "      → Should NOT see: Salary, Performance Rating, SSN, Admin Notes"
echo ""
echo "   □ 3. Test as PI"
echo "      → Should see: All fields EXCEPT Security Clearance"
echo "      → Should see: Admin Notes (group-based)"
echo ""
echo "   □ 4. Test as SecurityOfficer"
echo "      → Should see: Public fields + Security Clearance"
echo "      → Should NOT see: Email, Salary, SSN (unless also in other groups)"
echo ""
echo "   □ 5. Test as AdminUser"
echo "      → Should see: ALL fields (full access)"
echo ""
echo "   □ 6. Test different query formats"
echo "      → Table, List, JSON, CSV, Broadtable formats"
echo "      → Verify filtering works in all formats"
echo "      → NOTE: Template format not available (class doesn't exist in this SMW version)"
echo ""
echo "   □ 7. Test factbox on individual pages"
echo "      → Visit Employee pages directly"
echo "      → Verify SMW factbox shows only permitted properties"
echo ""
echo "   □ 8. Test cache behavior"
echo "      → Log in as PublicUser, view Query:Employees"
echo "      → Log out, log in as PI, view same page"
echo "      → Should see different columns (cache should vary by user)"
echo ""
echo "   □ 9. Test edge cases"
echo "      → Empty/null values"
echo "      → Multiple values per property"
echo "      → Properties with no visibility level set (should default to visible)"
echo ""
echo "📊 PROPERTY VISIBILITY SUMMARY"
echo ""
echo "   PUBLIC (Level 0):"
echo "   • Name, Department, Office Location"
echo ""
echo "   INTERNAL (Level 10):"
echo "   • Email, Phone, Hire Date"
echo ""
echo "   PRIVATE (Level 20):"
echo "   • Salary, Performance Rating, Emergency Contact"
echo ""
echo "   PI ONLY (Level 30):"
echo "   • SSN, Background Check Status"
echo ""
echo "   GROUP-BASED:"
echo "   • Admin Notes (sysop, pi)"
echo "   • Security Clearance (security, sysop)"
echo ""
echo "=========================================="
echo "Happy Testing! 🚀"
echo "=========================================="
