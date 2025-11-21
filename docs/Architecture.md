# FieldPermissions Architecture

## Overview

FieldPermissions uses a service-oriented architecture to intercept Semantic MediaWiki (SMW) queries and filter out data based on user permissions. 

For **SMW 6.x**, it employs a **Tier 2** filtering strategy, overriding key `ResultPrinter` classes to enforce visibility during the rendering phase.

## Key Components

### 1. Service Layer (`src/Services`)

- **VisibilityLevelStore**: CRUD operations for visibility levels stored in `fp_visibility_levels` table.
- **GroupLevelStore**: Manages mappings between user groups and max visibility levels in `fp_group_levels` table.
- **VisibilityResolver**: Resolves the visibility requirements for a given SMW Property (looking up `Has visibility level` and `Visible to`).
- **PermissionEvaluator**: The core logic that determines if a `User` can view a specific property based on its level and allow-list.
- **ResultPrinterVisibilityFilter**: The logic used by custom printers to filter `PrintRequest` columns and data values.
- **SmwQueryFilter**: Handles Factbox filtering.
- **VisibilityEditGuard**: Protects against unauthorized changes to visibility settings.

### 2. Database Schema

- **fp_visibility_levels**: Defines named levels (e.g., Public=0, Internal=10).
- **fp_group_levels**: Maps user groups to their maximum numeric level.

### 3. SMW Integration (Tier 2)

The extension integrates with SMW by overriding standard `ResultPrinter` classes. This ensures that all property values are checked against permissions before being displayed.

1. **Printer Override Hook**: `SMW::ResultPrinter::Register`
   - This hook replaces SMW's standard printers (Table, List, Template, etc.) with `Fp*` subclasses from this extension.

2. **Custom Printers** (`src/SMW/Printers/`):
   - **Mechanism**: Each custom printer (e.g., `FpTableResultPrinter`, `FpListResultPrinter`) uses `PrinterFilterTrait`.
   - **Filtering**: Before rendering, the printer modifies the `QueryResult` object using reflection to remove `PrintRequest` objects (columns) that the user is not allowed to see.
   - **Benefit**: By removing the request for data entirely, SMW does not fetch or display the restricted column, ensuring secure and performant filtering.

3. **Factbox (`SMW::Factbox::BeforeContentGeneration`)**:
   - Filters properties shown in the Factbox at the bottom of pages.
   - Uses the standard property list modification approach supported by SMW.

### 4. Permission Logic

1. **User Profile**: A user's profile is calculated based on their groups. The highest numeric level from all their groups is their "Max Level".
2. **Property Level**: A property's level is determined by its `Has visibility level` annotation.
3. **Evaluation**:
   - If property has `Visible to` groups, and user is in one -> **Allow**.
   - If user's Max Level >= Property Level -> **Allow**.
   - Otherwise -> **Deny**.

## Security

- **Format Coverage**: Major output formats (Table, List, Template, JSON, CSV) are covered by custom overrides.
- **Edit Protection**: The `VisibilityEditGuard` hooks into `getUserPermissionsErrors` and `MultiContentSave` to prevent unauthorized users from changing visibility settings or adding visibility restrictions to properties.
