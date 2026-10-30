# ADR 0003: Branch Editor role and membership capabilities

## Status

Accepted. The clause restricting Editor-role members is superseded by [ADR 0011](0011-membership-only-grants-page-access.md).

## Context

Branch editors need page primitives, but object-level access must depend on branch membership. Membership also needs to restrict non-administrators who otherwise have Editor-role page access. Classic editing and REST author checks require `edit_others_pages`.

## Decision

Register role `jp_branch_editor`, label "Branch Editor", with `read` and `upload_files`. For restricted users, `user_has_cap` adds `edit_pages`, `edit_others_pages`, and `publish_pages`. Do not grant `edit_published_pages`, `edit_private_pages`, `read_private_pages`, or any `delete_*`; object access is decided by `map_meta_cap`. A Branch Editor without a group has no page access. Membership restricts every non-administrator, including Editor-role users.

## Consequences

The required `edit_others_pages` grant allows author reassignment on authorized pages; this is accepted (confirmed 2026-10-28). The same grant is required for core's author check when Gutenberg saves meta boxes such as Yoast SEO on pages written by others. Mapping object meta caps avoids broad role-based authority. Editor-role users lose their native all-pages access while assigned to a group.
