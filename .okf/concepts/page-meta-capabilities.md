---
type: concept
title: Page meta capabilities
description: Verified WordPress page capability mapping and user capability-filter behavior.
tags: [wordpress, capabilities, pages]
timestamp: 2026-10-27T00:00:00Z
---

# Meta capability mapping

`map_meta_cap` receives `($caps, $cap, $user_id, $args)` through the filter. Nested `map_meta_cap('edit_post')` calls from non-public/non-private `read_post` checks and post-meta capability checks pass through the filter again. Edit and read checks for revisions resolve to the parent post. Page edit requirements depend on author and status: editing another user's published page requires `edit_others_pages` and `edit_published_pages`; another user's private page additionally requires `edit_private_pages`. Privacy-policy pages add `manage_privacy_options`. Deleting the front page or posts page requires `manage_options`.

# User capabilities

`user_has_cap` receives `($allcaps, $caps, $args, $user)`. Multisite super administrators bypass this filter. On single-site, `is_super_admin` means the user has `delete_users`.

# Design implications

- Repository ADR `docs/adr/0003-branch-editor-role-and-membership-capabilities.md` uses membership-driven primitive grants while retaining object-level checks.
- Repository ADR `docs/adr/0008-layered-server-side-enforcement.md` accounts for nested meta-capability mapping and revision resolution.

# Citations

[1] `wp-includes/capabilities.php:188-289, 214-220, 314-318, 436-445, 879` (WordPress 7.1.3).
[2] `wp-includes/class-wp-user.php:824` (WordPress 7.1.3).
