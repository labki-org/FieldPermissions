# Configuration Guide

PropertyPermissions is configured primarily through database-backed settings that you manage via the `Special:ManageVisibility` page.

Visibility behavior is enforced automatically across all SMW query outputs using custom `ResultPrinters`.

## 1. Managing Visibility Levels

Go to `Special:ManageVisibility`. Under **Visibility Levels**, create new levels with:

**Name**

- Unique identifier (e.g., `public`, `internal`, `private`)
- Letters, numbers, and underscores recommended

**Numeric Level**

- Number indicating how restrictive the level is
- `0` → Public; higher numbers → more restricted
- Example: `0 = Public`, `10 = LabMembers`, `20 = PIOnly`

**Page Title (optional)**

- Link to a descriptive wiki page (e.g., `Visibility:Internal`)
- Enables property annotations such as `[[Has visibility level::Visibility:Internal]]`
- System resolves the linked page or level name to the configured numeric level

## 2. Managing Group Visibility Permissions

Under **Group Permissions** in `Special:ManageVisibility`, map user groups to their maximum visibility level.

Example:

| Group       | Max Level      |
| ----------- | -------------- |
| `user`      | `0 (Public)`   |
| `lab_member`| `10 (Internal)`|
| `pi`        | `20 (Private)` |

**How it works**

- Users inherit all of their groups’ levels.
- Effective max level = highest level among all groups.
- This max level is used to determine whether they may view a property.

## 3. Protecting SMW Properties

Edit the property page (e.g., `Property:Salary`) and add one or both annotations:

**A. Restrict by Level**

```wikitext
[[Has visibility level::Visibility:Private]]
```

- PropertyPermissions resolves the linked page (`Visibility:Private`) or the raw identifier (`Private`) to the configured numeric level.
- Example: if `Private = 20`, only users with max level ≥ 20 can see `Salary`.

**B. Restrict by Specific Group**

```wikitext
[[Visible to::sysop]]
[[Visible to::hr_manager]]
```

- Allows specific groups to see the property regardless of numeric levels.
- If any allowed group matches the user’s groups → property is visible.
- Otherwise, numeric level rules apply.

Use this for allow-lists like “Only HR sees phone numbers” or “Only PIs see grant budgets.”

## 4. Edit Protection Rules

To prevent privilege escalation, users without `fp-manage-visibility` may **not**:

- Edit `Visibility:` pages (or any page used for level metadata)
- Edit restricted `Property:` pages (those with `Has visibility level` or `Visible to`)
- Insert or modify `[[Has visibility level::...]]` or `[[Visible to::...]]` anywhere

Enforced via `GetUserPermissionsErrors` and `MultiContentSave`.

## 5. How Visibility is Applied

For each property in an SMW query, visibility is granted if:

- The user belongs to one of the property’s `Visible to` groups **or**
- The user’s max visibility level ≥ the property’s level

Otherwise:

- Columns are removed from tables/lists/templates
- Keys are removed from JSON/CSV exports
- Properties are removed from Factbox

Filtering occurs in:

- Custom `Fp*` ResultPrinters (table, list, template, JSON, CSV, etc.)
- Factbox filtering via `SMW::Factbox::BeforeContentGeneration`

Result: sensitive data is never fetched or rendered for unauthorized users.

