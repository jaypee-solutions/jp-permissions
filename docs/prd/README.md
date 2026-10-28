---
title: JP Permissions — Page Branches PRD
aliases:
  - Page Branches Product Requirements
  - JP Permissions PRD
tags:
  - prd
  - jp-permissions
status: draft
updated: 2026-10-28
---
# JP Permissions: Page Branches

## Overview

JP Permissions’ Page Branches feature lets a central site administrator delegate editing of page-tree sections to volunteer editors in clubs and associations, while retaining administrator control over group configuration and page moves/deletion. Authorization follows page ancestry, including nested branches; editors work in the WordPress page editor, including Gutenberg.

Example page tree:

```text
Football
├── Teams
│   ├── Youth
│   └── Adults
└── Training
Tennis
Swimming
```

A branch group can assign Football and its descendants to one volunteer team, with nested roots such as Youth assigned additionally to a narrower team. Group permissions union across memberships.

## Reading order

1. [Requirements](requirements.md) — scope, requirements, permission matrix, and acceptance criteria.
2. [Glossary](../../GLOSSARY.md) — shared terminology.
3. [Architecture](architecture.md) — components, APIs, hooks, UI, and enforcement.
4. [Security model](security-model.md) — trust boundaries, threats, mitigations.
5. [Test strategy](test-strategy.md) — fixtures, authorization matrix, AC mapping, E2E, and CI.
6. [Implementation plan](implementation-plan.md) — backlog, estimates, milestones, and risks.
7. [Decisions](decisions.md) — accepted ADR index and open defaults.
8. [ADRs](../adr/0001-build-purpose-built-plugin.md) — accepted records, [directory index](../adr/).
9. [OKF research bundle](../../.okf/index.md) — verified WordPress and ecosystem facts.

## Status and scope

This documentation set is a draft implementation specification for a single-site WordPress plugin. Core scope is page-branch editing by groups, with server-side authorization, controlled subpage creation, administrator-only moves and deletion, and group administration. Multisite, custom post types, WooCommerce, media isolation, frontend visibility/editing, general ACL, and other out-of-scope items are listed in [Requirements](requirements.md). The estimated Must/Should effort is 32 hours; M1–M3 (29 hours) gate the first production site, and M4 adds Playwright E2E after go-live.

## Verified facts vs assumptions

WordPress-core and ecosystem claims backed by research are cited in the OKF concepts and linked from the relevant design sections. Claims that the research plan marks **UNVERIFIED** remain explicitly labelled that way and must not be treated as established behavior. Product defaults not confirmed by the user are listed as open questions in [Decisions](decisions.md); they guide implementation unless superseded, but are assumptions rather than verified facts.
