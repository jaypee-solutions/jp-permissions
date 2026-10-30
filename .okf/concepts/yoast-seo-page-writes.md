---
type: concept
title: Yoast SEO page writes
description: Verified Yoast SEO free save paths for page SEO metadata in the block editor and their interaction with core page capability checks.
tags: [wordpress, yoast, compatibility, pages]
timestamp: 2026-10-28T00:00:00Z
---

# Page SEO metadata save path

Yoast SEO free 28.8 registers its meta keys for all post types without REST exposure; REST exposure with an `edit_post` auth callback is registered only for the `post` subtype. Page SEO fields therefore do not save through the REST page endpoint.

For pages in the block editor, Yoast's fields live in a classic meta box. After the REST save, the editor POSTs the meta-box base form plus meta-box forms to `post.php` with `action=editpost`, adding `post_author` (the page's author) and `user_ID` (the current user). Core verifies the `update-post_{ID}` nonce and calls `edit_post()`, which checks `edit_post` for the page and rejects an author differing from the current user unless the user has `edit_others_pages`. The payload contains no `parent_id` or `menu_order`, so stored values are kept. Yoast's `save_postdata` runs on the `wp_insert_post` action, verifies its own `yoast_free_metabox` nonce, and writes meta only; it performs no capability check of its own.

# Page row writes

Outside bundled vendor code, Yoast SEO free 28.8 calls `wp_insert_post` only in its Elementor integration and `wp_delete_post` only in task-list tasks that delete the default "Hello World" post and "Sample Page". It has no `_wp_old_slug` handling, so it adds no slug-change redirects. Bundled `vendor` and `vendor_prefixed` code was not audited.

# Gated features

Advanced metadata fields (for example robots and canonical settings) are available only with the `wpseo_edit_advanced_metadata` capability or when the `disableadvanced_meta` option is `false`.

# Design implications

- Restricted users can save Yoast SEO fields on authorized pages only because membership grants `edit_others_pages`; otherwise core rejects the meta-box save for pages authored by someone else.
- Yoast's meta-box save passes through `edit_post()` and `wp_update_post`, so the page write guards apply; runtime confirmation remains a manual compatibility check.

# Citations

[1] `inc/class-wpseo-meta.php:100-120, 286-336` (Yoast SEO 28.8).
[2] `admin/metabox/class-metabox.php:77-83, 463-498` (Yoast SEO 28.8).
[3] `src/integrations/third-party/elementor.php:358`; `src/task-list/application/tasks/delete-hello-world.php:88`; `src/task-list/application/tasks/delete-sample-page.php:74` (Yoast SEO 28.8).
[4] `wp-includes/js/dist/edit-post.js:9916-9950`; `wp-admin/includes/post.php:21-71, 2551-2600`; `wp-admin/post.php:236-239` (WordPress 7.1.3).
