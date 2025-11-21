# Configuration Guide

FieldPermissions is configured primarily through the database via the `Special:ManageVisibility` page.

## Managing Visibility Levels

1. Navigate to `Special:ManageVisibility`.
2. Under "Visibility Levels", you can add new levels.
   - **Name**: A unique name for the level (e.g., `public`, `internal`, `confidential`).
   - **Numeric Level**: An integer value.
     - `0`: Usually Public.
     - Higher numbers indicate higher restriction.
   - **Page Title**: Optional. Link to a wiki page that describes this level (e.g., `Visibility:Internal`). This helps in using semantic properties like `[[Has visibility level::Visibility:Internal]]`.

## Managing Group Permissions

1. Navigate to `Special:ManageVisibility`.
2. Under "Group Permissions", you can assign a Maximum Visibility Level to a user group.
3. Users in that group will be able to see any property with a visibility level less than or equal to their group's max level.
4. If a user is in multiple groups, their effective level is the maximum of all their groups.

## Protecting Properties

To protect a property, edit its page (e.g., `Property:Salary`) and add semantic annotations.

### Has visibility level

Sets the minimum numeric level required to view values of this property.

```wikitext
[[Has visibility level::Visibility:Private]]
```

The system resolves `Visibility:Private` to its configured numeric level. You can also use the level name if you have a property setup for it, but linking to a page is recommended for SMW clarity.

### Visible to

Explicitly allows a specific user group to see the property, regardless of level.

```wikitext
[[Visible to::sysop]]
```

## Edit Protection

To prevent users from bypassing permissions:
- Only users with `fp-manage-visibility` right (default: sysops) can edit pages in the `Visibility:` namespace (if used).
- Only users with `fp-manage-visibility` right can edit Property pages that have active visibility restrictions.
- Users cannot add `[[Has visibility level::...]]` or `[[Visible to::...]]` to any page unless they have the management right.

