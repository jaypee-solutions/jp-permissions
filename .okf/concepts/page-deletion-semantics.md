---
type: concept
title: Page deletion semantics
description: Verified WordPress hooks and structural effects for deleting, trashing, and untrashing pages.
tags: [wordpress, deletion, pages]
timestamp: 2026-10-27T00:00:00Z
---

# Page lifecycle

`pre_delete_post`, `pre_trash_post`, and `pre_untrash_post` can short-circuit by returning a non-null value. `before_delete_post` fires before deletion; core reparents a deleted page's children to its parent using `$wpdb->update`. `deleted_post($post_id, $post)` fires after deletion. Trashing and untrashing change only the target and proceed through `wp_update_post`. A daily cron force-deletes auto-drafts older than seven days.

# User deletion

`delete_user` and `deleted_user` fire with `($id, $reassign, $user)`.

# Design implications

- Repository ADR `docs/adr/0007-deletion-admin-only.md` preserves core behavior for administrator deletion and denies restricted-user deletion.
- Repository ADR `docs/adr/0008-layered-server-side-enforcement.md` uses pre-operation short-circuit hooks for trash, restore, and deletion.

# Citations

[1] `wp-includes/post.php:3871, 3887, 3894-3908, 3996, 4111, 4131, 4193, 4231, 8381-8393` (WordPress 7.1.3).
[2] `wp-admin/includes/user.php:386, 469` (WordPress 7.1.3).
