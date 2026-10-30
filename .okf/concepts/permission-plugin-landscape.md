---
type: concept
title: Permission plugin landscape
description: Verified facts about related WordPress permission plugins and platform support.
tags: [wordpress, plugins, permissions]
timestamp: 2026-10-27T00:00:00Z
---

# Existing plugins

BU Section Editing was closed on WordPress.org on 2024-12-05. PublishPress Permissions is Press Permit Core's free edition; whether page-branch editing is available in free or Pro is **UNVERIFIED**. Nested Pages, Simple Page Ordering, and Yoast Duplicate Post write paths are **UNVERIFIED**.

# Platform facts

WordPress 7.1.3 was released on 2026-10-06. WordPress recommends PHP 8.3 or later. WP-CLI runs as user 0 unless `--user` is passed: **UNVERIFIED**.

# Design implications

- Repository ADR `docs/adr/0001-build-purpose-built-plugin.md` records why the product is purpose-built and retains the uncertainty around existing alternatives.
- Repository ADR `docs/adr/0008-layered-server-side-enforcement.md` defines system-context trust separately from user requests.

# Citations

[1] [BU Section Editing WordPress.org listing](https://wordpress.org/plugins/bu-section-editing/) (closed 2024-12-05).
[2] [Press Permit Core WordPress.org listing](https://wordpress.org/plugins/press-permit-core/) (PublishPress Permissions free edition).
[3] [WordPress requirements](https://wordpress.org/about/requirements/) (PHP 8.3+ recommendation).
[4] [WP-CLI configuration handbook](https://make.wordpress.org/cli/handbook/references/config/) (user 0 behavior **UNVERIFIED**).
