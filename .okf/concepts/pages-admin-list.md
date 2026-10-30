---
type: concept
title: Pages admin list
description: Verified WordPress Pages list (edit.php) query, hierarchy, pagination, and count behavior.
tags: [wordpress, pages, admin]
timestamp: 2026-10-28T00:00:00Z
---

# Query

With the default ordering, `wp_edit_posts_query()` lists hierarchical post types with `orderby` `menu_order title`, `posts_per_page` `-1`, and `fields` `id=>parent`, so the Pages list loads every matching page in one query. Hierarchical display is used only for that default ordering; total items for pagination then come from the query's `post_count`, so restricting the query (for example with `post__in` in `pre_get_posts`) also yields correct pagination.

# Hierarchy rendering

Pages whose parent is absent from the result set are "orphans": after all top-level trees are printed, the remaining pages are shown flat at level 0 on the last page. A filtered list whose top pages are not top-level site pages therefore loses indentation for those subtrees. Searching ignores hierarchy entirely.

# Status counts

The status view links ("All", "Published", "Draft", …) use `wp_count_posts( $post_type, 'readable' )`, which counts the whole site independent of the list query. The `wp_count_posts` filter can adjust these counts.

# Design implications

- Filtering the Pages list for restricted users is a single query restriction; pagination stays correct, view counts do not without a `wp_count_posts` filter.

# Citations

[1] `wp-admin/includes/post.php:1239-1323` (WordPress 7.1.3).
[2] `wp-admin/includes/class-wp-posts-list-table.php:154-199, 289-300, 849-927, 959-1013` (WordPress 7.1.3).
