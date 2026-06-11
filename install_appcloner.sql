-- ============================================================
-- nuBuilder 5 - App Cloner Module Registration
-- Run this ONCE after uploading files (e.g. in phpMyAdmin).
-- Compatible with the nub5 schema (nu_forms + nu_menus).
-- ============================================================

-- ─── 1. Register as a form in nu_forms ──────────────────────────────────────
INSERT IGNORE INTO `nu_forms` (
    `form_code`,
    `form_name`,
    `form_table`,
    `form_description`,
    `form_layout`,
    `form_settings`,
    `form_active`,
    `form_pk_type`,
    `form_table_mode`
) VALUES (
    'appcloner',                        -- form_code  (unique slug, used by menu target)
    'App Cloner',                       -- form_name
    NULL,                               -- form_table (no DB table – custom module)
    'Clone or export the nuBuilder 5 application database and files.',
    NULL,                               -- form_layout (custom module, no JSON layout needed)
    JSON_OBJECT(
        'custom_module', 'appcloner',   -- tells the router to load modules/appcloner/appcloner.php
        'type', 'custom'
    ),
    1,                                  -- form_active
    'autoincrement',                    -- form_pk_type
    'existing'                          -- form_table_mode
);

-- ─── 2. Add a menu item in nu_menus ─────────────────────────────────────────
--  Sits at the bottom of the menu (order 99), under no parent (menu_parent_id = 0)
INSERT IGNORE INTO `nu_menus` (
    `menu_parent_id`,
    `menu_code`,
    `menu_label`,
    `menu_type`,
    `menu_target`,
    `menu_icon`,
    `menu_order`,
    `menu_active`,
    `menu_role_access`
) VALUES (
    0,                                  -- top-level menu item
    'appcloner',                        -- menu_code (unique)
    'App Cloner',                       -- menu_label
    'form',                             -- menu_type  (matches nu_menus ENUM)
    'appcloner',                        -- menu_target → matches form_code above
    'copy',                             -- menu_icon  (Feather icon name)
    99,                                 -- menu_order
    1,                                  -- menu_active
    JSON_ARRAY('globeadmin', 'admin')   -- menu_role_access: only admins see this
);

-- ─── Notes ───────────────────────────────────────────────────────────────────
-- • The /temp/ folder must be writable by your web server.
--   The background worker writes progress JSON files there.
-- • To remove the module:
--     DELETE FROM nu_forms  WHERE form_code  = 'appcloner';
--     DELETE FROM nu_menus  WHERE menu_code  = 'appcloner';

SELECT 'App Cloner registered in nu_forms + nu_menus. Refresh nuBuilder 5 to see it in the menu.' AS result;
