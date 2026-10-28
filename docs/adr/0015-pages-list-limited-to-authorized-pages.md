# ADR 0015: Pages list shows restricted users only their authorized pages

## Status

Accepted

## Context

An unfiltered Pages list shows a volunteer all of a site's pages, including other branches' draft titles (threat T-07), although they can edit only their branch. The core Pages list loads all matching pages in one query and paginates from that result, so restricting the query keeps pagination correct; status counts come from `wp_count_posts` and stay site-wide (`.okf/concepts/pages-admin-list.md`).

## Decision

For restricted users, the Pages list query is limited to their authorized pages. There is no "All pages" view for them. Unrestricted users see the unchanged list.

## Consequences

| Alternative | Result |
|---|---|
| Show all pages | Clutter and other branches' draft titles visible |
| "My branches" view by default, "All" still available | Same code plus a view link; draft titles still visible under "All" |
| Authorized pages only (chosen) | Focused list; closes the Pages-list draft-title disclosure |

Status counts above the list still reflect the whole site until a `wp_count_posts` filter is added (Could). Subtrees whose top page is not a site top-level page, such as a nested root, render flat at the end of the list.
