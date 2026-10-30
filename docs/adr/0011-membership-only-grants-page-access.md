# ADR 0011: Membership only grants page access

## Status

Accepted. Supersedes the clause of [ADR 0003](0003-branch-editor-role-and-membership-capabilities.md) that made membership restrict Editor-role users.

## Context

ADR 0003 made group membership restrict every non-administrator, including Editors who natively edit all pages. That is a deny rule, contradicting the union-only model of [ADR 0004](0004-additive-branch-authorization.md). It also fails open: a corrupt option, deactivation, or uninstall silently returns full page access to Editor members, and Editor members keep `read_private_pages` for foreign branches.

## Decision

Membership only adds page access; it never removes access a user's role already grants. A member is a restricted user only when their role-derived capabilities lack `edit_others_pages`; membership grants and per-page scoping apply to restricted users only. Editors and administrators in a group are unrestricted and the admin UI marks them as such. To confine a staff member to branches, assign the Branch Editor or Author role.

## Consequences

| Alternative | Result |
|---|---|
| Membership restricts every non-administrator (ADR 0003) | Can confine Editors, but is a deny rule, fails open, and surprises administrators |
| Membership only grants (chosen) | Corrupt configuration, deactivation, and uninstall can only remove plugin grants, never widen access |

An Editor cannot be confined to a branch while keeping site-wide post editing. Administrators need no special case for restriction because their role grants `edit_others_pages`.
