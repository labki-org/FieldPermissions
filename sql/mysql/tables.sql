-- PropertyPermissions Schema (MySQL)

CREATE TABLE /*_*/fp_visibility_levels (
    vl_id INT AUTO_INCREMENT PRIMARY KEY,
    vl_name VARBINARY(255) NOT NULL UNIQUE,
    vl_page_title VARBINARY(255) NULL,
    vl_numeric_level INT NOT NULL
) /*$wgDBTableOptions*/;

CREATE TABLE /*_*/fp_group_levels (
    gl_group_name VARBINARY(255) NOT NULL PRIMARY KEY,
    gl_max_level INT NOT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*_*/fp_vl_numeric_idx ON /*_*/fp_visibility_levels (vl_numeric_level);
