---
type: concept
title: Target site profile
description: Size and default plugin stack of the club sites JP Permissions Page Branches targets.
tags: [jp-permissions, requirements, compatibility]
timestamp: 2026-10-28T00:00:00Z
---

# Scale

Confirmed by the product owner on 2026-10-28: the first target site has about 500 pages or fewer, 30 volunteer editors or fewer, and 10 branch groups or fewer.

# Default plugin stack

Every target site starts with these plugins:

| Plugin slug | Relevance to page writes |
|---|---|
| `wordpress-seo` | Yoast SEO; saves per-page SEO metadata from the block editor ([details](/concepts/yoast-seo-page-writes.md)) |
| `burst-statistics` | None expected |
| `complianz-gdpr` | May create legal pages as an administrator |
| `limit-login-attempts-reloaded` | None expected |
| `nextgen-gallery` | Own gallery storage and capabilities; not page writes |
| `shariff` | None expected |
| `simple-cloudflare-turnstile` | None expected |
| `smart-slider-3` | Own slider storage and capabilities; not page writes |
| `two-factor` | None expected |
| `wpforms-lite` | Own form storage; not page writes |
| `wp-mail-smtp` | None expected |

The relevance column is an assessment, not verified against plugin source, except for Yoast SEO.

# Design implications

- Pages are edited in the block editor (Gutenberg); no page builder is part of the stack.
- Classic Editor, Nested Pages, Yoast Duplicate Post, and Simple Page Ordering are not part of the default stack.
- Yoast SEO is the only default plugin expected to write page data alongside core.

# Citations

[1] Product-owner statement, grilling session 2026-10-28.
