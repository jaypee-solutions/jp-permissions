# ADR 0008: Layered server-side enforcement

## Status

Accepted. L2/L3 also enforce the root slug and status lock ([ADR 0012](0012-branch-root-slug-and-status-admin-only.md)) and the menu-order rule ([ADR 0013](0013-page-order-admin-only.md)).

## Context

WordPress core checks meta capabilities in UI, REST, and XML-RPC paths, but `wp_insert_post` and `wp_update_post` do not independently check those capabilities. Parent and trash changes also pass through write paths that need explicit guarding.

## Decision

Apply layers: L1 `map_meta_cap` object authorization; L2 `rest_pre_insert_page` parent checks for REST create/update; L3 `wp_insert_post_data` checks page inserts, parent changes, and transitions into/out of trash; L4 `pre_trash_post`, `pre_untrash_post`, and `pre_delete_post` reject restricted-user lifecycle actions. Guards apply only in restricted context: current user is restricted and `wp_doing_cron()` is false. System context (user ID 0 or cron) is trusted and unrestricted. Direct `$wpdb` writers are outside the trust boundary.

## Consequences

Core surfaces receive object-level checks while low-level writes are separately guarded. Multiple layers reduce path gaps but cannot protect against direct database writes. Trusted system operations remain available for maintenance and cron.
