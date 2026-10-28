# ADR 0013: Page order is administrator-only

## Status

Accepted. Extends [ADR 0006](0006-page-moves-admin-only.md) from parent to menu order.

## Context

The draft let restricted users change `menu_order` while [ADR 0010](0010-gutenberg-parent-ui-hiding.md) hides the Gutenberg `page-attributes` panel that holds both parent and order, and Quick Edit still exposes order. The effective rule depended on the screen used.

## Decision

Page tree structure, meaning parent and menu order, is administrator-only. Restricted users cannot change the `menu_order` of an existing page; an unchanged or omitted value is allowed, as for parent. Pages restricted users create keep the default order. The rule is enforced server-side on every supported write path.

## Consequences

| Alternative | Result |
|---|---|
| Order editable server-side, hidden only in Gutenberg | Rule depends on the editing screen |
| Order editable with a custom order control | Extra UI work for a low-value change |
| Structure administrator-only (chosen) | One consistent rule; administrators order sibling pages |

Volunteers' new sibling pages sort by the theme's fallback ordering until an administrator orders them.
