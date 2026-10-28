# ADR 0007: Deletion is administrator-only

## Status

Accepted

## Context

Trash, restore, and permanent deletion can remove or structurally detach pages from delegated branches. Core reparents children to the deleted page's parent on permanent deletion, but trashing changes only the target page.

## Decision

Restricted users cannot trash, restore, or permanently delete any page, including roots. Administrators retain core behavior: trashing a root leaves descendants in place and structurally authorized; permanently deleting a page reparents its children, so deleting a root moves its subtree out of the branch (fail-closed loss of access). On `deleted_post` for a page remove its ID from all group roots; on `deleted_user` remove that ID from all group members.

## Consequences

| Alternative | Result |
|---|---|
| Permit restricted-user deletion | Could detach or destroy delegated content and alter authority |
| Permit restricted-user trash of non-root pages, restore admin-only | Reversible, but trashed parents leave published descendants without their parent |
| Permit trash only for pages without children | Narrower, but adds a second rule for little gain |
| Deny restricted-user trash, restore, and delete (chosen) | Admins alone control lifecycle; descendant access follows core structural behavior |

Root cleanup prevents stale references; user cleanup prevents stale membership. Admin root deletion intentionally removes access to the reparented subtree.

Unpublishing (draft or private) is how restricted users take a page off the site. Mistaken or obsolete drafts accumulate until an administrator removes them; this is accepted (confirmed 2026-10-28).
