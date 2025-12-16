# FieldPermissions Architecture

## Overview

FieldPermissions provides fine-grained, property-level visibility control for Semantic MediaWiki (SMW).

For SMW 6.x, it implements a Tier-2 filtering architecture, which overrides SMW’s `ResultPrinter` classes so that visibility is enforced during query rendering, after SMW builds the query but before it retrieves and formats results.

All filtering occurs inside custom `Fp* ResultPrinters`, ensuring restricted properties are removed from the `QueryResult` prior to data retrieval.

## Key Components

### 1. Service Layer

Located in `src/` and wired through `ServiceWiring.php`.

**VisibilityLevelStore**

- Stores and retrieves visibility levels (numeric + name + optional title).
- Backed by `fp_visibility_levels`.

**GroupLevelStore**

- Maps MediaWiki user groups to their maximum allowed visibility level.
- Backed by `fp_group_levels`.

**VisibilityResolver**

- Reads SMW property metadata to determine:
  - `Has visibility level`
  - `Visible to`

**PermissionEvaluator**

- Computes a user’s effective visibility profile and determines whether the user may view a property.

**ResultPrinterVisibilityFilter**

- Used by `Fp*` printers to filter out disallowed `PrintRequest` columns before SMW processes them.

**SmwQueryFilter**

- Used for Factbox property filtering.

**VisibilityEditGuard**

- Prevents unauthorized edits to:
  - Visibility configuration pages (`Visibility:*`)
  - Restricted `Property:` pages
  - Wikitext visibility annotations

### 2. Database Schema

Two MediaWiki-managed tables (installed via `LoadExtensionSchemaUpdates`):

| Table                | Description                                                                 |
| -------------------- | --------------------------------------------------------------------------- |
| `fp_visibility_levels` | Defines named visibility levels (e.g., Public=0, Internal=10, PIOnly=20).   |
| `fp_group_levels`      | Maps user groups to their maximum numeric visibility level.                |

### 3. SMW Integration (Tier-2 Filtering)

#### How Integration Works

Unlike Tier-1 filtering (which manipulates SMW Query objects directly), Tier-2 filtering overrides the `ResultPrinter` layer so that visibility logic executes as SMW renders output.

#### 3.1 Printer Overrides: ResultPrinter Mapping

FieldPermissions does not use `SMW::ResultPrinter::Register` directly. Instead, it overrides:

- `smwgResultFormats` within `SMW::Settings::BeforeInitializationComplete`
- The global `$smwgResultFormats` array during `SetupAfterCache`
- Reinforces the override via `ExtensionFunctions`

This ensures SMW instantiates:

- `FpTableResultPrinter`
- `FpListResultPrinter`
- `FpTemplateResultPrinter`
- `FpJsonResultPrinter`
- `FpCsvResultPrinter`

These subclasses wrap SMW’s own printers but add column-level filtering.

#### 3.2 Custom Printers (`src/SMW/Printers/`)

All custom printers use a shared `PrinterFilterTrait`, which:

- Accesses the private `mPrintRequests` / `m_printRequests` property via reflection.
- Identifies the corresponding `DIProperty` for each `PrintRequest`.
- Uses `ResultPrinterVisibilityFilter` to determine whether the user may see that property.
- Removes unauthorized `PrintRequests` from the query result.

**Critical:** By removing the `PrintRequest` before SMW processes results, SMW never fetches, formats, or exposes restricted values.

#### 3.3 Factbox Filtering

- Hook: `SMW::Factbox::BeforeContentGeneration`
- The Factbox receives an array of properties.
- `SmwQueryFilter` removes any property the current user is not permitted to view.
- SMW then renders the reduced Factbox normally.

## Permission Logic

### User Profile Resolution

Each user receives a visibility profile created by:

- Collecting user groups (including implicit ones)
- Normalizing them
- Looking up each group’s max level
- Selecting the highest numeric level

### Property Visibility Resolution

For each SMW property:

- `Has visibility level` is converted to a numeric threshold.
- All `Visible to` entries are normalized into group names.

### Final Evaluation

A property is visible if either:

- The user belongs to a group listed in `Visible to`, **or**
- The user’s max visibility level ≥ the property’s level.

Otherwise:

- The property is removed from the result set.
- The Factbox hides it.
- It is excluded from all query formats (table, list, JSON, CSV, template, etc.).

## Security Model

### Full Coverage of Query Output

All major SMW formats are filtered through custom printers:

- Table (`table`, `broadtable`)
- Lists (`list`, `ul`, `ol`)
- Template (`template`)
- Exports (`json`, `csv`, `dsv`)
- Default format

### Edit Protection

`VisibilityEditGuard` secures:

- SMW property pages containing visibility metadata
- Visibility definition pages
- Attempts to insert `[[Has visibility level::…]]` or `[[Visible to::…]]` without proper permission

### Cache Safety

To avoid visibility leaks via parser cache:

- `PageRenderingHash` incorporates the user’s `maxLevel` and group list.
- Each distinct visibility profile receives its own cache variant.
