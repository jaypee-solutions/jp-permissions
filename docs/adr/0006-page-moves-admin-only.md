# ADR 0006: Page moves are administrator-only

## Status

Accepted. Extended to menu order by [ADR 0013](0013-page-order-admin-only.md).

## Context

Changing a page parent moves it across branch boundaries and can alter delegated access. REST validates a supplied parent for existence, while classic and Quick Edit paths also pass parent changes into post updates.

## Decision

Choose Option B: restricted users can never change `post_parent` on an existing page. Compare submitted parent to stored parent; an unchanged or omitted value is allowed. Administrators may move pages.

| Criterion | Option A: allow moves within authorized branches | Option B: prohibit all restricted-user moves |
|---|---|---|
| Complexity | Must resolve destination and source authorization and define edge cases | Simple stored-parent comparison |
| Security | Risk of cross-branch transfer or authority change | Prevents restricted-user branch transfer |
| Usability | More flexible | Editors cannot reorganize hierarchy |
| Gutenberg | Must validate destination selection | Hide parent control; still enforce server-side |
| Tests | Many source/destination and nesting combinations | Changed vs unchanged/omitted parent |

## Consequences

The policy intentionally sacrifices editor move usability for simpler and stronger branch boundaries. REST, classic `edit_post()`, Quick Edit, and bulk edit must share the same server rule; omitted parent remains safe because `wp_update_post` preserves stored values.
