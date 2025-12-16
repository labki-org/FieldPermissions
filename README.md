# FieldPermissions - Fine-Grained Field Visibility for MediaWiki + Semantic MediaWiki

FieldPermissions provides property-level access control in MediaWiki using Semantic MediaWiki (SMW).

It allows you to restrict the visibility of individual SMW properties based on:

- Visibility Levels (numeric thresholds you define)
- User Groups (explicit allow-lists)

The extension filters data at the ResultPrinter level, securing all SMW query outputs (table, list, JSON, CSV, template, etc.).

Fully compatible with MediaWiki 1.39+ and SMW 6.x.

## Key Features

### Visibility Defined in SMW

Restrictions are declared using Semantic MediaWiki properties on `Property:` pages:

- `Has visibility level`
- `Visible to`

### Database-Backed Configuration

A management UI (`Special:ManageVisibility`) allows administrators to:

- Define visibility levels (e.g., Public = 0, Internal = 5, PI = 10)
- Map user groups to max allowed level

### Tier-2 Visibility Filtering

All SMW result printers (table, list, json, csv, etc.) are replaced with custom `Fp*` printers that:

- Inspect each `PrintRequest` (column)
- Resolve its visibility metadata
- Remove it if the viewer lacks access

This prevents restricted fields from being fetched or displayed.

### Factbox Filtering

Semantic Factboxes suppress restricted properties before rendering.

### Edit Enforcement

A `VisibilityEditGuard` ensures that only authorized users can:

- Edit visibility definition pages (`Visibility:*`)
- Edit SMW property pages with visibility controls
- Add/modify visibility annotations inside page content

### Secure Caching

Parser cache varies by user visibility profile, preventing privilege leakage.

## Installation

1. Clone into extensions directory

   ```bash
   cd /path/to/mediawiki/extensions
   git clone https://github.com/your-repo/FieldPermissions.git
   ```

2. Enable the extension

   ```php
   wfLoadExtension( 'FieldPermissions' );
   ```

3. Run schema updates

   ```bash
   php maintenance/update.php
   ```

## Usage Guide

### 1. Create Visibility Levels

Go to:

`Special:ManageVisibility`

A "visibility level" has:

- Name (e.g., Public, Private, PIOnly)
- Numeric Level (higher = more private)
- Optional Page Title (e.g., `Visibility:PIOnly`)

Example:

| Name     | Numeric |
| -------- | ------- |
| Public   | 0       |
| Internal | 5       |
| Private  | 10      |

### 2. Map User Groups → Max Level

Still in `Special:ManageVisibility`:

| User Group | Max Level |
| ---------- | --------- |
| user       | 0 (Public) |
| lab_member | 5 (Internal) |
| pi         | 10 (Private) |

### 3. Protect SMW Properties

On a `Property:` page:

**Restrict by level**

```wikitext
[[Has visibility level::Visibility:Private]]
```

You may reference visibility levels by:

- Page title (`Visibility:Private`)
- Level name (`Private`)

**Restrict by explicit user group(s)**

```wikitext
[[Visible to::sysop]]
[[Visible to::lab_manager]]
```

Group names are normalized case-insensitively.

### 4. Queries Automatically Filtered

Use SMW queries normally:

```wikitext
{{#ask: [[Category:Employee]]
 |?Email
 |?Salary
 |?Performance Score
}}
```

If the user cannot view `Salary` or `Performance Score`, those columns simply do not appear.

All formats are filtered:

- table
- list
- template
- json
- csv
- dsv
- default

### 5. Factbox Filtering

The Factbox at the bottom of a page also omits restricted properties automatically.

## How It Works (Architecture)

### Tier-2 Printer Override

SMW builds a registry of printer classes using the setting:

`$smwgResultFormats`

FieldPermissions overrides this mapping early:

- During `SMW::Settings::BeforeInitializationComplete`
- Reinforced in `SetupAfterCache`
- Optionally enforced again via `ExtensionFunctions`

This guarantees that the SMW factory instantiates:

- `FpTableResultPrinter`
- `FpListResultPrinter`
- `FpJsonResultPrinter`
- `FpCsvResultPrinter`
- `FpTemplateResultPrinter`

Each custom printer:

- Identifies the property associated with each `PrintRequest`
- Checks `Has visibility level` and `Visible to` metadata
- Consults the viewer's visibility profile
- Removes unauthorized fields before SMW fetches or renders them

### Parser Cache Safety

MediaWiki normally caches pages without considering user permissions.

FieldPermissions injects:

- viewer max visibility level
- viewer group list

into the page rendering hash, ensuring:

- Admin cache ≠ Lab Member cache ≠ Public cache

### Edit-Time Enforcement

The edit guard prevents unauthorized modification of visibility rules by restricting:

- Edits to `Visibility:*` pages
- Edits to protected `Property:` pages
- Adding/changing `[[Has visibility level::...]]` or `[[Visible to::...]]` in content

## Configuration

Additional configuration docs can be placed at `docs/Configuration.md`.

## License

GPL-2.0-or-later
