# ADR 0010: Hide the Gutenberg parent UI

## Status

Accepted

## Context

The Gutenberg `page-attributes` panel renders the parent row and parent options are not permission-filtered. `removeEditorPanel` is available. A DataViews `parent` field exists in post fields; whether it renders as an editable control in the post editor is UNVERIFIED.

## Decision

For restricted users on page edit screens enqueue `assets/js/editor.js` (no build step; handle `jp-permissions-editor`, dependencies `wp-data`, `wp-dom-ready`, `wp-editor`) and call `wp.data.dispatch('core/editor').removeEditorPanel('page-attributes')` on DOM ready. This hides the parent row and menu order as UI convenience only. Classic Editor and Quick Edit parent controls are not hidden in MVP; the server rejects changes.

## Consequences

The common Gutenberg parent control is removed without relying on client UI for security. Menu-order editing is hidden along with page attributes. If the UNVERIFIED DataViews field renders an editable parent control, server-side rejection remains authoritative and UI behavior requires follow-up confirmation.
