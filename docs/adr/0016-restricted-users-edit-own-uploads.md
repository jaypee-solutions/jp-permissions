# ADR 0016: Restricted users may edit their own uploads

## Status

Accepted

## Context

Branch Editors have `upload_files` but not `edit_posts`. Attachments use post capabilities, so editing one's own attachment requires `edit_posts`. Since WordPress 7.1 the block editor processes images client-side in secure contexts and uploads sub-sizes through the attachment sideload route, which requires `edit_post` on the attachment; crop/rotate and attachment details need the same (`.okf/concepts/media-capabilities.md`). Without a change, Branch Editor uploads lack sub-sizes and cannot be edited.

## Decision

For restricted users, `map_meta_cap` maps `edit_post` on an attachment they authored to `upload_files`. Other users' attachments keep core mapping, and attachment deletion keeps core mapping. The media library stays shared: restricted users can insert any attachment.

## Consequences

| Alternative | Result |
|---|---|
| Grant `edit_posts` | Also exposes the Posts menu and blog drafting |
| Disable client-side media processing for restricted users | Sub-sizes work; crop/rotate and attachment details still fail |
| Own-attachment mapping (chosen) | Uploads, sub-sizes, crop/rotate, and details work for own uploads only |

A restricted user can alter an image they uploaded even where an administrator reused it outside their branch; this is accepted with the shared library.
