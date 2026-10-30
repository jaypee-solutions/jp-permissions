---
type: concept
title: Admin menu access
description: Verified WordPress admin-screen access check and the submenu-collapse trap when removing "Add New" items.
tags: [wordpress, admin, capabilities, menu]
timestamp: 2026-10-28T00:00:00Z
---

# Access check

Every wp-admin screen load includes `wp-admin/menu.php`, which builds `$menu`/`$submenu`, records entries the user lacks the capability for in `$_wp_menu_nopriv`/`$_wp_submenu_nopriv`, and calls `user_can_access_admin_page()`; on failure it fires `admin_page_access_denied` and dies with "Sorry, you are not allowed to access this page." This check is separate from the screen's own capability check.

# Submenu collapse

`menu.php` removes a top-level menu's submenu when only one item remains and it points at the parent slug. If a plugin removes "Add New Page" on `admin_menu`, the Pages submenu collapses, `get_admin_page_parent()` returns an empty string for `edit.php?post_type=page`, and access falls back to `$_wp_menu_nopriv['edit.php']` (the Posts menu). Users with `edit_pages` but without `edit_posts`, such as Branch Editors, are then denied the Pages screen.

# Design implications

- Remove "Add New Page" for restricted users after the access check (for example on `admin_init`), not on `admin_menu`.

# Citations

[1] `wp-admin/menu.php`, `wp-admin/includes/plugin.php` (`get_admin_page_parent`, `user_can_access_admin_page`) (WordPress 7.1.3); reproduced on the Page Branches test site, 2026-10-28.
