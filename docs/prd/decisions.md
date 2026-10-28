---
title: Decisions
aliases:
  - JP Permissions ADR Index
tags:
  - prd
  - jp-permissions
status: draft
updated: 2026-10-27
---
# Decisions

## Accepted architecture decisions

| ADR | Title | Status | Decision |
|---|---|---|---|
| [0001](../adr/0001-build-purpose-built-plugin.md) | Build a purpose-built plugin | Accepted | Implement the focused “Page Branches” feature in JP Permissions rather than reusing a broad or unsuitable plugin. |
| [0002](../adr/0002-groups-in-single-option.md) | Store groups in one option | Accepted | Store normalized groups in the autoloaded `jp_permissions_groups` option; last concurrent write wins. |
| [0003](../adr/0003-branch-editor-role-and-membership-capabilities.md) | Branch Editor role and membership capabilities | Accepted; Editor-restriction clause superseded by 0011 | Register `jp_branch_editor`; group membership grants limited page primitives, while object authorization remains membership-based. |
| [0004](../adr/0004-additive-branch-authorization.md) | Additive branch authorization | Accepted | Union group roots and authorize a page when it or an ancestor is a root. |
| [0005](../adr/0005-page-creation-via-add-subpage.md) | Page creation via Add subpage | Accepted | Create pages only beneath authorized parents through the Add subpage flow; REST follows the same parent rule. |
| [0006](../adr/0006-page-moves-admin-only.md) | Page moves are administrator-only | Accepted | Restricted users cannot change an existing page’s parent; omitted or unchanged parent is allowed. |
| [0007](../adr/0007-deletion-admin-only.md) | Deletion is administrator-only | Accepted | Restricted users cannot trash, restore, or delete pages; administrative root deletion removes root authority. |
| [0008](../adr/0008-layered-server-side-enforcement.md) | Layered server-side enforcement | Accepted | Enforce at meta-capability, REST, write-guard, and deletion boundaries; UI hiding is not authorization. |
| [0009](../adr/0009-integration-test-stack.md) | Integration test stack | Accepted | Preserve stub unit tests, add wp-env WordPress integration tests and plain Playwright E2E. |
| [0010](../adr/0010-gutenberg-parent-ui-hiding.md) | Gutenberg parent UI hiding | Accepted | Hide the Gutenberg `page-attributes` panel for restricted users as a UI convenience; retain server enforcement. |
| [0011](../adr/0011-membership-only-grants-page-access.md) | Membership only grants page access | Accepted | Membership never removes role-granted access; only members whose role lacks `edit_others_pages` are restricted. |
| [0012](../adr/0012-branch-root-slug-and-status-admin-only.md) | Branch root slug and status are administrator-only | Accepted | Restricted users edit root content but cannot change a root's slug or status; descendants stay fully editable. |
| [0013](../adr/0013-page-order-admin-only.md) | Page order is administrator-only | Accepted | Restricted users cannot change an existing page's menu order; unchanged or omitted order is allowed. |
| [0014](../adr/0014-site-navigation-out-of-scope.md) | Site navigation is out of scope | Accepted | The plugin does not manage navigation; documentation requires sites to list branch pages automatically. |
| [0015](../adr/0015-pages-list-limited-to-authorized-pages.md) | Pages list limited to authorized pages | Accepted | Restricted users' Pages list query is limited to their authorized pages; no "All pages" view for them. |
| [0016](../adr/0016-restricted-users-edit-own-uploads.md) | Restricted users may edit their own uploads | Accepted | `edit_post` on a restricted user's own attachment maps to `upload_files`; others' attachments and deletion keep core mapping. |

## Open questions

None. All product defaults were confirmed in the 2026-10-28 review; minimum versions are WordPress 7.1 and PHP 8.0 (NFR-07).

## Upstream to template

The wp-env integration test harness and repository-local integration workflow are candidates to upstream to `jaypee-solutions/wp-plugin-template`; keep this plugin’s `.github/workflows/integration.yml` separate from template-owned `ci.yml`.
