-- PropertyPermissions Schema (SQLite)

CREATE TABLE /*_*/fp_visibility_levels (
    vl_id INTEGER PRIMARY KEY AUTOINCREMENT,
    vl_name TEXT NOT NULL UNIQUE,
    vl_page_title TEXT NULL,
    vl_numeric_level INTEGER NOT NULL
);

CREATE TABLE /*_*/fp_group_levels (
    gl_group_name TEXT NOT NULL PRIMARY KEY,
    gl_max_level INTEGER NOT NULL
);

CREATE INDEX /*_*/fp_vl_numeric_idx ON /*_*/fp_visibility_levels (vl_numeric_level);

