---
type: concept
title: Block editor page UI
description: Verified WordPress page editor panel, parent-option, query, and UI creation facts.
tags: [wordpress, gutenberg, pages]
timestamp: 2026-10-27T00:00:00Z
---

# Page attributes panel

The `page-attributes` panel renders `ParentRow`; `removeEditorPanel` is available. Parent options are fetched 100 per page without permission filtering. The server-side `rest_page_query` filter is applied to the page REST query.

# Other editor paths

The Link UI's create-page flow posts a title and draft status without a parent. A DataViews `parent` field exists in post fields, but whether it renders as an editable control in the post editor is **UNVERIFIED**.

# Design implications

- Repository ADR `docs/adr/0010-gutenberg-parent-ui-hiding.md` hides the page-attributes panel for restricted users as a UI convenience only.
- Repository ADR `docs/adr/0008-layered-server-side-enforcement.md` requires server enforcement independent of editor UI state.

# Citations

[1] `wp-includes/js/dist/editor.js:83129-83146, 78150-78157, 79181, 82930-82955` (WordPress 7.1.3).
[2] `wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php:420-447` (WordPress 7.1.3).
[3] `wp-includes/js/dist/block-library.js:13154-13182`; `wp-includes/js/dist/editor.js:74416-74434, 78156` (WordPress 7.1.3).
