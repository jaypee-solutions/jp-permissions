---
type: concept
title: Media capabilities
description: Verified WordPress attachment capability mapping and block-editor upload paths, including client-side media processing.
tags: [wordpress, media, capabilities, rest]
timestamp: 2026-10-28T00:00:00Z
---

# Attachment capabilities

The `attachment` post type uses the `post` capability type with `create_posts` mapped to `upload_files` and `map_meta_cap` enabled. Attachments have status `inherit`, so `edit_post` on a user's own attachment maps to `edit_posts`; another user's attachment additionally needs `edit_others_posts`.

# REST upload paths

Creating media requires `upload_files` and, when attaching to a post, `edit_post` on that post. The `post-process`, `edit` (crop/rotate), and `sideload` media routes all resolve to the attachment update check, i.e. `edit_post` on the attachment.

# Client-side media processing

Since WordPress 7.1, client-side media processing is enabled by default in a secure context (HTTPS, `localhost`, or `*.localhost`) and is filterable via `wp_client_side_media_processing_enabled`. When enabled, the block editor uploads the original without server sub-sizes and then uploads generated sub-sizes through `/wp/v2/media/{id}/sideload`. Without client-side processing, the editor calls `/wp/v2/media/{id}/post-process` only to retry sub-size generation after a failed upload.

# Design implications

- A user with `upload_files` but without `edit_posts` can upload media but cannot sideload sub-sizes, crop/rotate, or edit details of their own uploads. Runtime effect on Branch Editor uploads in the block editor is UNVERIFIED.

# Citations

[1] `wp-includes/post.php:95-115`; `wp-includes/capabilities.php:188-266` (WordPress 7.1.3).
[2] `wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php:115-118, 404-430, 1052-1074, 2515-2517` (WordPress 7.1.3).
[3] `wp-includes/media.php:6580-6605`; `wp-includes/default-filters.php:696-700`; `wp-includes/js/dist/media-utils.js:1577-1599`; `wp-includes/js/dist/api-fetch.js:392-396` (WordPress 7.1.3).
