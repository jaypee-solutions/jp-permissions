# ADR 0004: Additive branch authorization

## Status

Accepted

## Context

Users may belong to multiple groups, and roots may nest. Authorization must behave consistently regardless of author or ancestor post status.

## Decision

A page is authorized for a user when it or any ancestor, via `get_post_ancestors`, is a root of any group the user belongs to. Group permissions are unioned; there are no deny rules. Nested roots are additive: Football editors can edit Youth and its subtree when Youth is separately assigned to Youth Coaches, while Youth Coaches receive only Youth. Ancestry is structural and status-agnostic, with no depth limit. Cycles rely on `get_post_ancestors` loop termination. The privacy-policy page retains core's additional `manage_privacy_options` requirement.

## Consequences

The rule is author-independent and lets nested delegations coexist without overriding broader grants. Cyclic ancestry terminates and does not authorize absent a matching root. Privacy-page capability behavior remains aligned with WordPress core.

Per-page exclusions inside a branch were rejected (confirmed 2026-10-28): a page that must be off-limits to a branch's members is moved out of the branch by an administrator.
