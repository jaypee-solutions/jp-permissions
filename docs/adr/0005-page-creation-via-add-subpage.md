# ADR 0005: Create pages through Add subpage

## Status

Accepted

## Context

WordPress's default new-page flow creates an auto-draft without a parent. Restricted users must not create root pages or place pages in another branch, while REST creation under an authorized parent remains useful for API parity.

## Decision

Restricted users create pages only beneath authorized parents. The Pages row action "Add subpage" uses `admin-post.php?action=jp_permissions_add_subpage&parent=ID&_wpnonce=…`; the handler verifies nonce and `current_user_can('jp_permissions_add_subpage', $parent)`, creates an `auto-draft` with that parent, schedules core auto-draft cleanup if needed, and redirects to its editor. The action is available to any user with the meta capability, including administrators. Restricted visits to `post-new.php?post_type=page` redirect to Pages with `jp_permissions_notice=use_add_subpage`. REST create is allowed only with an authorized parent; missing or unauthorized parent returns 403 `jp_permissions_forbidden_parent`.

## Consequences

The dedicated flow preserves parent context from the first draft. The generic root-creation UI and Link UI page creation are unavailable to restricted users; direct REST callers can create under an authorized parent.
