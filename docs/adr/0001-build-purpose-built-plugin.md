# ADR 0001: Build a purpose-built plugin

## Status

Accepted

## Context

Clubs need page-branch delegation, while role editors such as Members, URE, and WPFront do not provide branch delegation. BU Section Editing was closed on WordPress.org on 2024-12-05 for a security issue. PublishPress Permissions is broad; its free/Pro split for page-branch editing is UNVERIFIED.

## Decision

Build the "JP Permissions" plugin with the "Page Branches" feature as a purpose-built solution (early estimate: about 1,000 LOC; no line-count cap, see NFR-09).

## Consequences

The implementation can focus on the required branch model rather than a general ACL. The project owns its security and maintenance burden. Reuse of existing products was rejected: role editors lack branch delegation, one relevant plugin was closed, and PublishPress Permissions scope remains UNVERIFIED.
