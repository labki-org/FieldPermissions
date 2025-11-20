# FieldPermissions MediaWiki Extension

FieldPermissions provides fine-grained, field-level access control in MediaWiki templates and Semantic MediaWiki (SMW) queries. It enables you to restrict field visibility based on permission levels or explicit user groups.

## Features

- **Level-based permissions**: Use symbolic level names (e.g., "public", "internal", "pi_only") that map to numeric values
- **Group-based permissions**: Restrict fields to specific user groups or named group sets
- **SMW integration**: Automatically filters SMW query results to hide private properties from unauthorized users
- **Cache-safe**: Multi-layer protection against parser cache, file cache, and CDN leaks
- **Fully configurable**: All permission mappings defined in LocalSettings.php - no hard-coded assumptions

## Installation

1. Clone or download this extension into your MediaWiki `extensions/` directory:
   ```bash
   cd /path/to/mediawiki/extensions
   git clone https://github.com/your-repo/FieldPermissions.git
   ```

2. Add the following to your `LocalSettings.php`:
   ```php
   wfLoadExtension( 'FieldPermissions' );
   ```

3. Configure permissions (see Configuration section below)

## Configuration

All permissions are configured in `LocalSettings.php`. The extension ships with no pre-defined group names, levels, or mappings.

### Permission Levels

Define symbolic level names and their numeric values:

```php
$wgFieldPermissionsLevels = [
    'public'    => 0,
    'internal'  => 10,
    'sensitive' => 20,
    'pi_only'   => 30,
];
```

Higher numeric values represent more restrictive access levels.

### Group Maximum Levels

Map user groups to their maximum accessible level:

```php
$wgFieldPermissionsGroupMaxLevel = [
    '*'          => 'public',      // Anonymous users
    'user'       => 'public',      // Registered users
    'lab_member' => 'internal',    // Lab members
    'lab_manager'=> 'sensitive',  // Lab managers
    'pi'         => 'pi_only',     // Principal investigators
    'sysop'      => 'pi_only',     // System administrators
];
```

Users can access any level up to and including their group's maximum level.

### Optional Group Sets

For convenience, define named sets of groups:

```php
$wgFieldPermissionsGroupSets = [
    'all_admins' => [ 'sysop', 'pi', 'lab_manager' ],
    'research_staff' => [ 'lab_member', 'lab_manager' ],
];
```

These can be used in the `#field-groups` parser function.

## Usage

### Level-based Permissions: `#field`

Use `#field` to restrict content based on permission levels:

```wikitext
! Salary
| {{#field:pi_only| [[Salary::{{{salary|}}}]] }}

! Home address
| {{#field:sensitive| {{{home_address|}}} }}

! Equipment Authorizations
| {{#field:internal|
     {{#arraymap:{{{equipment_authorizations|}}}|;|x|[[Equipment Authorization::x]]}}
  }}
```

**Syntax**: `{{#field:required_level|CONTENT}}`

- `required_level`: The symbolic level name (e.g., "public", "internal", "pi_only")
- `CONTENT`: The content to display if the user has access

If the user's maximum level is greater than or equal to the required level, the content is displayed. Otherwise, an empty string is returned.

### Group-based Permissions: `#field-groups`

Use `#field-groups` to restrict content to specific user groups:

```wikitext
! Admin-only notes
| {{#field-groups:sysop,lab_manager|{{{notes|}}}}}

! Research staff content
| {{#field-groups:research_staff|{{{internal_data|}}}}}
```

**Syntax**: `{{#field-groups:group1,group2,groupSetName|CONTENT}}`

- `group1,group2,groupSetName`: Comma-separated list of group names or group set names
- `CONTENT`: The content to display if the user belongs to any of the specified groups

If the user belongs to any of the specified groups (or groups in a named set), the content is displayed. Otherwise, an empty string is returned.

### SMW Integration

FieldPermissions automatically integrates with Semantic MediaWiki:

1. **Property Extraction**: When parsing templates, the extension scans for SMW property annotations (e.g., `[[PropertyName::value]]`) within `#field` blocks
2. **Query Filtering**: SMW query results are automatically filtered to hide properties that the current user doesn't have permission to view
3. **Cache Safety**: SMW query caches respect user permissions

**Example**:

In a template:
```wikitext
{{#field:pi_only| [[Salary::{{{salary|}}}]] }}
```

When a user without "pi_only" access runs an SMW query, the `Salary` property will be automatically filtered from the results.

## Examples

### Employee Information Template

```wikitext
{| class="wikitable"
! Name
| {{{name|}}}

! Email
| {{{email|}}}

! Salary
| {{#field:pi_only| {{{salary|}}} }}

! Home Address
| {{#field:sensitive| {{{home_address|}}} }}

! Department Notes
| {{#field:internal| {{{department_notes|}}} }}

! Admin Notes
| {{#field-groups:sysop,lab_manager| {{{admin_notes|}}} }}
|}
```

### PageForms Integration

FieldPermissions works seamlessly with PageForms. Simply wrap sensitive form fields:

```wikitext
{{{field|salary|input type=text|label=Salary}}}
{{#field:pi_only| {{{field|salary|}}} }}
```

### Complex Template with Arrays

```wikitext
! Equipment Authorizations
| {{#field:internal|
     {{#arraymap:{{{equipment_authorizations|}}}|;|x|
       [[Equipment Authorization::x]]
     }}
  }}
```

## Security Considerations

FieldPermissions implements multiple layers of cache protection:

1. **Parser Cache**: Disabled when field permissions are used
2. **Client Cache**: Disabled for pages using field permissions
3. **CDN Cache**: Max-age set to 0 for protected content
4. **Cache Hash**: Includes user permission level to prevent cross-user leaks
5. **SMW Query Cache**: Results filtered at query time based on user permissions

## Architecture

### Directory Structure

```
FieldPermissions/
├── extension.json
├── src/
│   ├── Hooks.php                    # Consolidated hooks (like Lockdown extension)
│   ├── ParserFunctions/
│   │   ├── FieldFunction.php
│   │   └── FieldGroupsFunction.php
│   ├── Permissions/
│   │   ├── PermissionChecker.php
│   │   ├── LevelPermissionChecker.php
│   │   ├── GroupPermissionChecker.php
│   │   ├── PermissionConfig.php
│   │   └── PropertyPermissionRegistry.php
│   ├── Services/
│   │   └── FieldPermissionsService.php
│   └── Utils/
│       ├── StringUtils.php
│       └── SMWPropertyExtractor.php
├── tests/
│   └── phpunit/
└── i18n/
```

**Note**: This extension follows the structural pattern of the [Lockdown extension](https://github.com/wikimedia/mediawiki-extensions-Lockdown), using a single consolidated `Hooks.php` file and modern MediaWiki hook handling via `HookHandlers`.

### Key Components

- **PermissionConfig**: Loads and validates configuration from LocalSettings.php
- **LevelPermissionChecker**: Compares user's maximum level to required level
- **GroupPermissionChecker**: Checks user group membership
- **PermissionChecker**: Facade that routes to appropriate checker
- **PropertyPermissionRegistry**: Global registry for SMW property permissions
- **SMWHooks**: Filters SMW query results based on permissions

## Testing

### PHP Tests

Run PHPUnit tests:

```bash
cd /path/to/FieldPermissions
composer install
vendor/bin/phpunit tests/phpunit/
```

### Code Quality

The extension includes several code quality tools:

**PHP Code Quality:**
```bash
composer test          # Run all PHP quality checks
composer phpcs         # Check PHP code style
composer fix           # Auto-fix PHP code style issues
composer phan          # Run static analysis
```

**JavaScript/JSON Code Quality:**
```bash
npm install            # Install Node.js dependencies
npm test               # Run ESLint and i18n validation
grunt test             # Same as npm test
```

The extension uses:
- **PHP CodeSniffer** for PHP code style checking
- **Phan** for static analysis
- **ESLint** for JSON and JavaScript linting
- **grunt-banana-checker** for i18n message validation

## Requirements

- MediaWiki >= 1.39
- PHP >= 7.4
- Semantic MediaWiki (optional, for SMW integration)

## License

[Specify your license here]

## Contributing

[Contributing guidelines]

## Support

[Support information]
