# FieldPermissions MediaWiki Extension

FieldPermissions provides fine-grained, field-level access control in MediaWiki using Semantic MediaWiki (SMW) properties. It enables you to restrict field visibility based on permission levels or explicit user groups, filtering data at the source.

**Compatible with SMW 6.x** using a secure "Tier 2" architecture (ResultPrinter Overrides).

## Features

- **Semantic Configuration**: Configure visibility using standard SMW properties (`Has visibility level`, `Visible to`) on property pages.
- **Database-Backed**: Visibility levels and group mappings are stored in the database and managed via a Special Page.
- **Output Filtering**: Automatically filters SMW query results (#ask, API, JSON, CSV, etc.) by overriding ResultPrinters to remove restricted columns before rendering.
- **Secure by Design**: Filters data at the rendering stage, ensuring that restricted properties are never requested or displayed.
- **Factbox Protection**: Filters properties displayed in the Factbox at the bottom of pages.

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

3. Run the update script to create database tables:
   ```bash
   php maintenance/update.php
   ```

## Usage

### 1. Define Visibility Levels

Go to `Special:ManageVisibility` (requires `fp-manage-visibility` right, default for sysops).

Create levels with numeric values. Higher numbers = more restrictive.
Example:
- **Public**: 0
- **Internal**: 10
- **Private**: 20

### 2. Assign Levels to User Groups

In `Special:ManageVisibility`, map user groups to their maximum allowed visibility level.
Example:
- **user**: Public (0)
- **lab_member**: Internal (10)
- **pi**: Private (20)

### 3. Protect Properties

On any Property page (e.g., `Property:Salary`), add the following semantic annotations:

**To restrict by level:**
```wikitext
[[Has visibility level::Visibility:Private]]
```
(Assuming you have a page `Visibility:Private` representing the level, or just use the name if configured).

**To restrict by specific group:**
```wikitext
[[Visible to::sysop]]
[[Visible to::hr_manager]]
```

### 4. Display Data

Use standard SMW queries. Data will be automatically filtered based on the viewing user's permissions.

```wikitext
{{#ask: [[Category:Employee]]
 |?Salary
 |?Email
}}
```

If a user does not have permission to see `Salary`, that column/field will be empty or removed.

## Configuration

See [docs/Configuration.md](docs/Configuration.md) for detailed configuration guide.

## Architecture

This extension uses a **Tier 2** filtering approach for SMW 6.x:
1. **Interception**: Hooks into `SMW::ResultPrinter::Register` to replace standard printers.
2. **Overrides**: Custom `Fp*` printers (Table, List, JSON, etc.) extend standard SMW printers.
3. **Filtering**: Inside the printer, the extension inspects the `QueryResult` and removes any `PrintRequest` (column) that the user is not authorized to view.
4. **Result**: SMW skips fetching and rendering data for the removed columns, ensuring security.

## License

GPL-2.0-or-later
