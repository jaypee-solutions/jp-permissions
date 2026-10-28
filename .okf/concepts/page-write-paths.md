---
type: concept
title: Page write paths
description: Verified WordPress REST, autosave, revision, classic edit, and insertion behavior for pages.
tags: [wordpress, pages, rest, editor]
timestamp: 2026-10-28T00:00:00Z
---

# REST writes

Page creation checks `create_posts` (`edit_pages`); setting another author requires `edit_others_pages`. Updates check `edit_post`; a supplied parent is checked for existence only. Publishing, scheduling, and private status require `publish_pages`. `rest_pre_insert_{post_type}` can abort create or update by returning `WP_Error`. REST deletion checks `delete_post`. The editor's publish action is gated by the `publish_posts` primitive.

# Autosaves and revisions

Autosave permission uses the parent controller update check and the parent's database preparation path, so `rest_pre_insert_page` runs. Same-author draft/auto-draft saves update in place. The client autosave payload contains title, excerpt, content, and meta (auto-drafts send `status: "draft"`), but not parent or author; editor saves only edited fields. Revision listing requires `edit_post` on the parent, and restore applies revision fields then saves the post.

# Admin and insertion paths

`post-new.php` loads `admin.php` (and fires `load-post-new.php`) before `get_default_post_to_edit()`, which inserts an “Auto Draft” with `auto-draft` status and no parent. The block edit form replaces that title with an empty initial edit, including when opened through `post.php`. Editing an existing post through `post.php?action=edit` requires `edit_post`.

Pages do not take `wp_insert_post`'s empty-content short circuit because they lack excerpt support. `wp_insert_post_parent` filters the parent; `wp_insert_post_data` filters data but cannot return an error. `wp_update_post` merges stored values, preserving an omitted `post_parent`.

Quick Edit checks `edit_post` and passes `post_parent` as `parent_id` to `edit_post()`. Bulk edit's empty or `-1` parent means no change. Classic author override is gated by `edit_others_posts`. The Link UI create-page action sends title and draft status without a parent.

# Slug changes

`wp_check_for_changed_slugs` returns early for hierarchical post types, so changing a published page's slug stores no `_wp_old_slug` and core adds no old-URL redirect. Because page permalinks embed ancestor slugs, renaming a page changes the URL of every descendant.

# Design implications

- Repository ADR `docs/adr/0005-page-creation-via-add-subpage.md` supplies an authorized parent for the UI creation path.
- Repository ADR `docs/adr/0006-page-moves-admin-only.md` guards explicit parent changes while allowing omitted values.
- Repository ADR `docs/adr/0008-layered-server-side-enforcement.md` covers core cap checks and low-level write paths.

# Citations

[1] `wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php:687-734, 893-940, 1446-1469, 1531, 1569-1603, 741-759, 944-963, 1065-1080` (WordPress 7.1.3).
[2] `wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php:189-223, 230-255`; `wp-includes/rest-api/endpoints/class-wp-rest-revisions-controller.php:170-210` (WordPress 7.1.3).
[3] `wp-includes/js/dist/editor.js:85769-85776, 78722-78760`; `wp-includes/js/dist/core-data.js:18286-18317, 18440-18467`; `wp-includes/js/dist/block-library.js:13154-13182`; `wp-includes/js/dist/editor.js:74416-74434` (WordPress 7.1.3).
[4] `wp-admin/admin.php:390`; `wp-admin/post-new.php:10, 66`; `wp-admin/includes/post.php:780-818`; `wp-admin/edit-form-blocks.php:146-151`; `wp-admin/post.php:146-151` (WordPress 7.1.3).
[5] `wp-includes/post.php:4695, 4868, 4978, 7575-7586`; `wp-admin/includes/ajax-actions.php:2099-2195`; `wp-admin/includes/post.php:21-76, 514-611` (WordPress 7.1.3).
