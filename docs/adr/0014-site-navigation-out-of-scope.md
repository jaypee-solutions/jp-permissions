# ADR 0014: Site navigation is out of scope

## Status

Accepted

## Context

If a site's navigation is a hand-curated menu or Navigation block, pages that restricted users create stay invisible until an administrator edits the navigation, keeping administrators in the loop for every new page.

## Decision

Page Branches does not manage navigation. User documentation requires sites to list branch pages automatically, for example with the Page List block or a subpage listing on branch pages.

## Consequences

| Alternative | Result |
|---|---|
| Auto-insert new pages into a menu | Extra scope, per-site menu configuration, and no editor control over placement |
| Grant members menu editing | Requires `edit_theme_options`, which exposes site-wide design |
| Out of scope, documented requirement (chosen) | No code; sites with curated menus keep an administrator step |
