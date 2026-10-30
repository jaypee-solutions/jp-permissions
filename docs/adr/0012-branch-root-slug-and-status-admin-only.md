# ADR 0012: Branch root slug and status are administrator-only

## Status

Accepted

## Context

A branch root is where administrator-owned site navigation hands over to a volunteer-owned branch. WordPress adds no old-slug redirect for hierarchical post types and page permalinks embed ancestor slugs, so renaming a root changes every URL in its branch without redirects (`.okf/concepts/page-write-paths.md`). Unpublishing a root breaks administrator-managed navigation.

## Decision

Restricted users may edit a branch root's content but cannot change its slug or status. The lock applies to any page that is a root of any branch group, regardless of which group authorizes the user, and is enforced server-side on every supported write path. Descendant pages remain fully editable, including slug and status.

## Consequences

| Alternative | Result |
|---|---|
| Roots fully editable | Volunteers can break branch URLs and administrator navigation |
| Root slug and status administrator-only (chosen) | Branch entry points stay stable; volunteers ask an administrator to rename or unpublish a root |
| Roots read-only for members | Volunteers cannot maintain their branch landing page |

Gutenberg still shows slug and status controls on roots; the server rejects changes. Nested roots are locked for members of broader groups too, so Football editors cannot rename Youth. Slug changes on non-root pages still break their descendants' URLs; this is accepted as the branch's own responsibility.
