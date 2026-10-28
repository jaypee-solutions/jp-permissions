---
title: Page Branches Requirements
aliases:
  - JP Permissions PRD
  - Branch delegation requirements
tags:
  - prd
  - jp-permissions
status: draft
updated: 2026-10-28
---

# 1. Problem statement and vision

Clubs and associations often have a central WordPress administrator but rely on volunteers to maintain separate subdivisions of a page tree. Native page capabilities do not provide safe branch delegation: granting page editing can expose unrelated pages, while manual workflows make ordinary updates dependent on the central administrator. **Page Branches**, a feature of the “JP Permissions” plugin, lets administrators assign page-tree branches to groups of volunteer editors. Editors manage their authorized branch using Gutenberg, while server-side authorization protects every supported write path.

Example: Football editors manage Football and its descendants; Youth Coaches manage Youth and its descendants; Tennis Editors manage Tennis and its descendants. Football editors inherit access to nested Youth, while Youth Coaches do not gain access to the rest of Football.

# 2. Goals and non-goals

## Goals

- Delegate editing of selected page branches to named groups, with additive membership and roots.
- Enforce authorization at server-side operation boundaries across supported WordPress write paths.
- Provide a minimal administrator interface to manage groups, roots, and members.
- Support page creation beneath authorized parents and normal editing/publishing of authorized pages.

## Non-goals

- Custom post types (CPTs).
- WooCommerce.
- Media isolation.
- Frontend visibility controls.
- Editorial workflows beyond per-group review mode (B-21).
- Multisite.
- Frontend editing.
- Replacing user management.
- General ACL functionality.
- Classic Editor UI polish.
- Moves by editors.
- Site navigation management; sites list branch pages automatically using a Page List block or subpage listing.

# 3. Personas

| Persona | Needs and constraints |
|---|---|
| Site administrator | Assigns roots and members, manages all pages and groups, and remains unrestricted. |
| Volunteer branch editor | Uses the Branch Editor role and edits only pages authorized through group membership; creates pages beneath authorized parents. |
| Staff editor with Editor role | Keeps native Editor access whether or not a member of a branch group; group membership does not restrict role-derived capabilities. |
| Developer/operator | Maintains the plugin and may perform trusted system-context operations; WP-CLI is unrestricted unless invoked with a user identity. |

## 4. Glossary

See [GLOSSARY.md](../../GLOSSARY.md) for shared terminology.

### Implementation definitions

| Term | Definition |
|---|---|
| Restricted user | Member of at least one branch group whose role-derived capabilities lack `edit_others_pages`. |
| Administrator | User with `manage_options`, which gates group administration. |
| System context | Current user ID 0 or `wp_doing_cron()` is true. |
| Authorized page | Page whose self or a `get_post_ancestors` ancestor is a root of any group the user belongs to. |

# 5. Functional requirements

## FR-01 — Branch groups (Must)

**Rules**
- Administrators can create, rename, and delete groups.
- Names are required, trimmed, 1–100 characters, and unique case-insensitively; invalid and duplicate names are rejected with error codes.
- A user may belong to multiple groups. Configuration is stored in the single autoloaded option `jp_permissions_groups`; group IDs are integers allocated from `next_id` and never reused.
- Corrupt or missing configuration is normalized to an empty configuration and grants no branch access.

**Acceptance criteria:** AC-01, AC-02, AC-27, AC-30.

## FR-02 — Root assignment (Must)

**Rules**
- Each group may have zero or more roots; a page may be a root in multiple groups.
- At save time, roots must be existing `page` posts and may not be `auto-draft` or trashed; invalid IDs are dropped/rejected.

**Acceptance criteria:** AC-03, AC-27.

## FR-03 — Inheritance (Must)

**Rules**
- A page is authorized when it or any structural ancestor is a root assigned to any group of which the user is a member.
- Group permissions form a union; there are no deny rules. Nested roots are additive: Football Editors retain access to nested Youth, while Youth Coaches receive only Youth.
- Inheritance has unlimited depth, does not depend on authorship, and remains structural/status-agnostic (draft or trashed ancestors still convey authority). Hierarchy cycles must terminate and fail closed.

**Acceptance criteria:** AC-04, AC-05, AC-06, AC-07, AC-08, AC-09, AC-10, AC-28.

## FR-04 — Page creation (Must)

**Rules**
- Restricted users may create pages only beneath an authorized, existing page parent; root creation is not allowed.
- The Pages-list “Add subpage” action creates an `auto-draft` child and redirects to its editor. Its GET admin-post action is `jp_permissions_add_subpage`, query argument `parent`, with nonce action `jp_permissions_add_subpage_{parent_id}`; the meta capability is `jp_permissions_add_subpage` with parent ID argument.
- The action is available to every user with that meta capability, including administrators. Invalid nonce or unauthorized/invalid parent is denied.
- Restricted users visiting `post-new.php?post_type=page` are redirected to the Pages list with `jp_permissions_notice=use_add_subpage` and an explanatory notice.
- REST page creation under an authorized parent remains allowed; missing/root or unauthorized parent returns HTTP 403 with `jp_permissions_forbidden_parent`.

**Acceptance criteria:** AC-11, AC-12, AC-13, AC-14, AC-15.

## FR-05 — Edit and publish (Must)

**Rules**
- Group membership adds the `edit_pages`, `edit_others_pages`, and `publish_pages` primitives to restricted users; it does not grant `edit_published_pages`, `edit_private_pages`, `read_private_pages`, or any `delete_*` primitive. Object authorization is decided by meta capabilities.
- Restricted users may edit content, title, template, password, and author on authorized pages. They may change slug and status on descendants, but not on any branch root; title and content of roots remain editable. Author reassignment on authorized pages is allowed.
- Restricted users may set authorized non-root pages to `draft`, `pending`, `publish`, `future`, or `private`; they may unpublish, schedule, and preview. They may not set status to or from `trash`. Root status changes are administrator-only.
- Authorized-page revision listing/restoration and preview work; unauthorized-page editing and draft reading are denied.
- Restricted users' Pages list shows only their authorized pages; status counts above the list may still reflect the whole site.
- Media library is shared, not isolated: restricted users can insert any attachment, may edit attachments they uploaded (sub-sizes, crop/rotate, details), cannot edit others' attachments, and cannot delete attachments.

**Acceptance criteria:** AC-05, AC-17, AC-18, AC-19, AC-20, AC-22, AC-31, AC-33, AC-35.

## FR-06 — Moves (Must)

**Rules**
- Restricted users cannot change the `post_parent` or `menu_order` of an existing page. Unchanged or omitted values are allowed. Administrators may move pages and change order.
- Restricted users creating pages must retain the default `menu_order` of 0; non-zero order on insert is rejected.

**Acceptance criteria:** AC-16, AC-17, AC-32.

## FR-07 — Deletion (Must)

**Rules**
- Restricted users cannot trash, untrash/restore, or permanently delete any page, including roots, through any supported path.
- Trashing a root as an administrator leaves its descendants in place; structural ancestry continues to authorize them.
- Permanently deleting a page reparents its children to its parent as WordPress core does. Permanently deleting a root therefore moves its subtree out of the branch and removes access through that root (fail closed).
- Permanent page deletion removes that page ID from all group roots; user deletion removes that user ID from all group memberships.

**Acceptance criteria:** AC-21, AC-23, AC-24, AC-25.

## FR-08 — Gutenberg (Must: server parity; Should: panel hiding)

**Rules**
- Server-side checks remain authoritative regardless of editor interface or hidden controls.
- For restricted users editing pages in Gutenberg, hide the `page-attributes` panel (parent and menu-order UI) using the `jp-permissions-editor` script (`assets/js/editor.js`; dependencies `wp-data`, `wp-dom-ready`, `wp-editor`). This is UI convenience only.
- Classic Editor and Quick Edit parent controls are not hidden in MVP; attempts to change a parent are rejected server-side.

**Acceptance criteria:** AC-16, AC-26.

## FR-09 — Administrator UI (Must)

**Rules**
- Provide Users → “Page Branches” at slug `jp-permissions-branches`, protected by `manage_options`; the load hook is `load-users_page_jp-permissions-branches`.
- Administrators can create/edit groups, add roots one per save from a hierarchical page dropdown, remove listed roots, select users, and delete groups with a confirmation. Save/delete forms use nonces `jp_permissions_save_group` and `jp_permissions_delete_group_{group_id}` respectively.
- Invalid/tampered root IDs and unknown user IDs are dropped; successful saves/deletes show a notice and validation errors preserve submitted values.
- Display effective group membership and roots in the list and user profile. Mark members whose role already edits all pages as unrestricted.

**Acceptance criteria:** AC-01, AC-02, AC-03, AC-27.

## FR-10 — Compatibility (Must document)

**Rules**
- Supported and conditional integrations are documented in the compatibility matrix below.
- Direct-SQL writers are outside the enforcement boundary; plugin-specific third-party write paths remain UNVERIFIED until manually checked.
- Multisite is unsupported; activation on multisite is refused.

**Acceptance criteria:** AC-05, AC-14, AC-16, AC-21, AC-34.

## FR-11 — Lifecycle (Must)

**Rules**
- Activation registers role `jp_branch_editor` with `read` and `upload_files`. Deactivation keeps the role and option: Branch Editors retain only those role capabilities; Editor-role users retain their native Editor access unchanged.
- Activation on multisite (`is_multisite()`) stops with a translated message before registering the role or writing the option.
- `Lifecycle::maybe_upgrade()` on `plugins_loaded` compares stored schema with `GroupRepository::SCHEMA = 1` and re-adds the role if missing.
- Uninstall registers via `register_uninstall_hook( PLUGIN_FILE, [ Lifecycle::class, 'uninstall' ] )`; it deletes `jp_permissions_groups`, changes every `jp_branch_editor` user's role to `subscriber`, and removes the role.

**Acceptance criteria:** AC-30.

## FR-12 — Branch Editor role (Must)

**Rules**
- The role slug is `jp_branch_editor`, label “Branch Editor”, with base capabilities `read` and `upload_files`.
- A Branch Editor in one or more groups is restricted to the union of authorized branches. Membership alone does not restrict users whose role-derived capabilities include `edit_others_pages`.
- A Branch Editor without a group has no page access (fail closed). Administrators remain unrestricted.

**Acceptance criteria:** AC-08, AC-09, AC-10, AC-27, AC-29, AC-30.

# 6. Non-functional requirements

| ID | Requirement |
|---|---|
| NFR-01 Performance | Anonymous front-end requests incur no plugin database queries beyond reading the autoloaded option. A capability check is O(ancestor depth), using the post object cache; memoize each user’s root set per request and reset on `update_option_jp_permissions_groups`. |
| NFR-02 Security | Follow WordPress security practices: capability checks at render and save boundaries; nonces for state-changing admin actions; sanitize input (`absint`, `sanitize_text_field` as appropriate); escape output; safe redirects; reject unauthorized parent/status/delete operations; do not expose custom unauthenticated endpoints. |
| NFR-03 Fail closed | Missing, malformed, or corrupt option grants no branch access. Invalid roots are dropped/rejected; unknown users are not granted membership. Cycles terminate without granting access. |
| NFR-04 Internationalization | Text domain is `jp-permissions`; all user-facing UI and error strings are translatable. |
| NFR-05 License | GPL-2.0-or-later. |
| NFR-06 Runtime dependencies | Add no new runtime dependencies. |
| NFR-07 Supported versions | Declare WordPress ≥ 7.1 and PHP ≥ 8.0 in the plugin header, `readme.txt`, and README; WordPress 7.1 is the tested and source-verified baseline (client-side media processing, ADR 0016). Test CI against latest WordPress (7.1.3 at writing). Single-site only. |
| NFR-08 OPcache safety | Do not generate code at runtime; implementation must be safe under OPcache. |
| NFR-09 Maintainability, accessibility, and scale | Keep the implementation minimal within the non-goals and ADRs (no line-count cap); pass WPCS via `composer lint`; use native admin markup and labels for every input. Target up to 500 pages, 30 restricted users, and 10 groups. |

# 7. Role and permission matrix

“Authorized pages only” means the operation is available only for pages authorized through the user's group roots. Site-level capabilities otherwise follow the user's base role.

| Operation | Administrator | Editor role (no group) | Branch Editor in a group | Branch Editor without a group | Subscriber |
|---|---|---|---|---|---|
| View Pages list | Yes | Yes | Yes; authorized pages only | No page access | No |
| Open editor on authorized page | Yes | Yes | Yes | No | No |
| Open editor on unauthorized page | Yes | Yes | No | No | No |
| Edit/publish/schedule/private authorized page | Yes | Yes | Yes | No | No |
| Create under authorized parent | Yes | Yes | Yes | No | No |
| Create at root | Yes | Yes | No | No | No |
| Create under unauthorized parent | Yes | Yes | No | No | No |
| Change parent of existing page | Yes | Yes | No | No | No |
| Change slug/status of a branch root | Yes | Yes | No | No | No |
| Change order of an existing page | Yes | Yes | No | No | No |
| Trash/restore/delete any page | Yes | Yes | No | No | No |
| Manage groups | Yes | No | No | No | No |
| Site settings/themes/plugins/users | Yes | No (base Editor role) | No | No | No |

# 8. FR-06 move policy: Option A vs Option B

| Criterion | Option A: allow moves within authorized branches | Option B: all existing-page moves administrator-only (selected) |
|---|---|---|
| Complexity | Must validate old and new branch authorization, including moves that alter descendant reachability. | Simple invariant: parent must remain unchanged. |
| Security | More transition and boundary cases; a move can change which group controls a subtree. | Stronger, easier-to-reason-about boundary; editors cannot transfer pages between branches. |
| Usability | More flexible for volunteer restructuring. | Less flexible; administrator must perform moves. |
| Gutenberg | Parent control can remain available but requires reliable validation. | Hide `page-attributes` panel for restricted users; server independently rejects changes. |
| Tests | Requires same-branch, cross-branch, root, and subtree transition cases. | Test changed parent rejection and unchanged/omitted parent acceptance across paths. |

# 9. Acceptance criteria

Fixtures are defined in [Test strategy](test-strategy.md): pages Home, Membership, Football → Teams → Youth/Adults, Football → Training, Tennis → Courts/Training, Swimming → Courses; groups G1–G4 (Football Editors, Tennis Editors, Swimming Editors, Youth Coaches); and users `admin`, `staff`, `fb`, `multi`, `youth`, `fb_staff`, `orphan`, `sub`. “Training” without qualification refers to Football → Training unless specified.

| ID | Given | When | Then |
|---|---|---|---|
| AC-01 | `admin` and the fixture users; existing groups G1–G4 | `admin` creates, renames, or deletes a group from Users → Page Branches | Each operation succeeds only with a valid nonce and `manage_options`; a non-administrator or invalid nonce is denied. |
| AC-02 | `admin` on the group form | A group name is empty, over 100 characters, or duplicates an existing name differing only by case | Save is rejected with the corresponding validation error code; valid trimmed 1–100-character unique names save. |
| AC-03 | Fixture pages plus a non-page post, auto-draft, trashed page, and unknown ID | `admin` saves a group's roots containing those IDs | Non-page, auto-draft, trashed, and unknown IDs are dropped or rejected; valid page roots remain. |
| AC-04 | G1 rooted at Football; `fb` belongs to G1; page Youth is at least three descendant levels below a G1 root | `fb` edits Youth | Authorization succeeds irrespective of author. |
| AC-05 | G1 rooted at Football; Tennis is outside G1; `fb` and `youth` attempt unauthorized page access | A restricted user requests a foreign page through `post.php` and REST, including a draft read | Editing and unauthorized draft reading are denied. |
| AC-06 | G1 Football Editors rooted at Football; G4 Youth Coaches rooted at Youth; users `fb` and `youth` | `fb` edits Youth and a Youth descendant; `youth` edits Youth but attempts Football Training | `fb` is authorized within the nested root; `youth` is limited to Youth and its descendants. |
| AC-07 | `multi` belongs to G2 and G3; G2 roots at Tennis and G3 at Swimming | `multi` edits Tennis and Swimming descendants | Both branches are authorized; unrelated Football/Home pages are not. |
| AC-08 | `fb_staff` has the Editor role and belongs to G1 rooted at Football | `fb_staff` edits Football and Tennis; group admin UI displays `fb_staff` | Both edits are allowed; member is marked unrestricted. |
| AC-09 | `admin` has `manage_options` | `admin` edits any fixture page and manages groups | All pages and group administration remain unrestricted. |
| AC-10 | `orphan` has Branch Editor role and belongs to no group | `orphan` attempts page access | No page access is granted. |
| AC-11 | `fb` belongs to G1 and Football Training is authorized | `fb` uses Add subpage on Training | An `auto-draft` child with Training as parent is created and the user is redirected to its editor. |
| AC-12 | `fb` belongs to G1; also present are unauthorized Tennis and a trashed or auto-draft parent | `fb` invokes Add subpage with unauthorized/invalid parent or invalid nonce | Request is denied; no child page is created. |
| AC-13 | `fb` is restricted | `fb` visits `post-new.php?post_type=page` | Redirect goes to the Pages list with `jp_permissions_notice=use_add_subpage` and the Add subpage instruction. |
| AC-14 | `fb` is restricted; G1 roots at Football | REST creates a page at root or under unauthorized Tennis | REST returns HTTP 403 with code `jp_permissions_forbidden_parent`. |
| AC-15 | `fb` is restricted; Football Training is authorized | REST creates a page with Training as parent | Creation succeeds. |
| AC-16 | `fb` edits authorized Football page; Tennis is outside the branch | Parent is changed through REST, classic `edit_post()`/`parent_id`, Quick Edit `post_parent`, or bulk edit | Every changed-parent attempt is rejected; stored parent is unchanged. |
| AC-17 | `fb` has an authorized page or an auto-draft child created under Training | Update omits parent, supplies its unchanged parent, or autosaves the auto-draft | Update/autosave succeeds and the stored parent remains unchanged. |
| AC-18 | `fb` has an authorized non-root page | `fb` publishes, schedules, or sets it private | Each status change succeeds. |
| AC-19 | `fb` is restricted and Tennis is unauthorized | `fb` attempts to publish or otherwise change status on Tennis | The operation is denied. |
| AC-20 | `fb` has an authorized page with revisions; Tennis is unauthorized | `fb` lists, previews, and restores an authorized revision, then attempts the same on Tennis | Authorized revision operations work; unauthorized-page operations are denied. |
| AC-21 | `fb` is restricted and has an authorized page (and a root where applicable) | `fb` trashes, untrashes/restores, or permanently deletes through functions, REST DELETE, admin actions, or bulk actions | Every deletion operation is denied. |
| AC-22 | `fb` is authorized for Football and a Football descendant | `fb` unpublishes or sets the descendant to draft, then makes the same status change on root Football | Descendant change succeeds; root change is denied with `jp_permissions_forbidden_root_change` and stored status is unchanged. |
| AC-23 | G1 contains Football as a root; `admin` permanently deletes Football | Permanent deletion completes | Football's ID is removed from every group's roots. |
| AC-24 | `fb` is a member of G1 | `admin` deletes user `fb` | `fb`'s ID is removed from every group's membership. |
| AC-25 | G1 roots at Football; `admin` trashes Football, then permanently deletes a root in a separate fixture state | Descendants are checked after each operation | Trashing leaves descendants structurally under the root and authorized; permanent deletion reparents its subtree out of the branch and descendant access fails closed. |
| AC-26 | Restricted user `fb` and administrator `admin` edit pages in Gutenberg | Each opens the page editor | Restricted user has no `page-attributes` panel; administrator retains it. Server rejects parent changes regardless of UI. |
| AC-27 | `orphan`, `fb`, and `fb_staff`; option is missing or corrupt/non-array | Each user attempts page access | `orphan` has no page access; `fb` receives no branch grants; `fb_staff` keeps native Editor access unchanged. |
| AC-28 | Fixture hierarchy is corrupted to contain a cycle; `fb` belongs to G1 | `fb` requests authorization for a page whose ancestry traverses the cycle | Ancestry evaluation terminates and authorization is denied. |
| AC-29 | Current user ID is 0, or execution is cron/WP-CLI without `--user` | A system-context operation writes a page | Branch restrictions do not block the operation. WP-CLI identity behavior is UNVERIFIED. |
| AC-30 | Plugin lifecycle state and fixture users include `admin`, `fb`, and `fb_staff` | Activate, deactivate, upgrade with missing role, then uninstall | Activation registers the role; deactivation preserves role and option; Branch Editors retain only `read`/`upload_files`, and `fb_staff` is unaffected; upgrade re-adds missing role; uninstall deletes option and role and demotes Branch Editor users to Subscriber without widening access. |
| AC-31 | G1 rooted at Football and G4 rooted at nested Youth; `fb` belongs to G1; `admin` is administrator | `fb` changes slug or status of root Football and nested root Youth via REST and Quick Edit; edits Football title/content; `admin` changes root slug/status | Restricted root slug/status changes are rejected with `jp_permissions_forbidden_root_change` and stored values unchanged; root title/content edits succeed; administrator changes succeed. |
| AC-32 | `fb` is restricted and has an authorized page; Training is an authorized parent; `admin` is administrator | `fb` changes `menu_order` via REST or Quick Edit, omits/supplies unchanged order, creates under Training with non-zero order; `admin` reorders | Changed order and non-zero insert are rejected with `jp_permissions_forbidden_order`; omitted/unchanged order and administrator reorder succeed. |
| AC-33 | `fb` belongs to G1; `multi` belongs to G2 and G3; `staff` has the Editor role | Each opens the Pages list | `fb` sees only Football and its descendants and `multi` only Tennis and Swimming subtrees, with correct pagination; neither sees Home, Membership, or other branches' drafts; `staff` sees all pages. |
| AC-34 | WordPress multisite | An administrator activates the plugin on a site or network-wide | Activation is refused with a translated message; no `jp_branch_editor` role or `jp_permissions_groups` option is created. |
| AC-35 | `fb` is restricted; `admin` has uploaded attachment A; client-side media processing is enabled | `fb` uploads an image to authorized Training via REST, sideloads a sub-size and updates alt text on that upload, then edits A and deletes its own upload | Upload, sideload, and own-attachment update succeed; editing A and deleting the upload are denied. |

# 10. Compatibility matrix (FR-10)

| Integration/path | Compatibility |
|---|---|
| Gutenberg | Supported; server enforcement is authoritative; restricted users' parent/order panel is hidden as a convenience. |
| Classic Editor | Not in target stack; classic `edit_post()` paths used by Quick Edit/Bulk Edit remain server-enforced. |
| REST API | Enforced for page reads/writes, parent/order changes, creation, root slug/status changes, and deletion as specified. |
| XML-RPC | Enforced by write guard and meta capabilities; broad `edit_pages` checks are followed by object checks. |
| WP-CLI | System context is unrestricted unless `--user` is supplied; behavior of default user ID 0 is UNVERIFIED. |
| Quick Edit / Bulk Edit | Server-enforced; changed parent/order and root slug/status are denied. |
| Yoast SEO (`wordpress-seo`) | Page SEO metadata saves through the block editor's meta-box POST to `edit_post()`/`wp_update_post`, so core `edit_post`, the `edit_others_pages` author check, and the write guards apply (source-verified for 28.8, see [OKF](../../.okf/concepts/yoast-seo-page-writes.md)); runtime confirmation pending B-16. |
| Direct-SQL writers | Bypass plugin enforcement; third-party plugin behavior is UNVERIFIED and must be checked under B-16. |
| Multisite | Unsupported; activation is refused. |

# Traceability: functional requirements to acceptance criteria

| Requirement | Acceptance criteria |
|---|---|
| FR-01 | AC-01, AC-02, AC-27, AC-30 |
| FR-02 | AC-03, AC-27 |
| FR-03 | AC-04–AC-10, AC-28 |
| FR-04 | AC-11–AC-15 |
| FR-05 | AC-05, AC-17–AC-20, AC-22, AC-31, AC-33, AC-35 |
| FR-06 | AC-16–AC-17, AC-32 |
| FR-07 | AC-21, AC-23–AC-25 |
| FR-08 | AC-16, AC-26 |
| FR-09 | AC-01–AC-03, AC-27 |
| FR-10 | AC-05, AC-14, AC-16, AC-21, AC-34 |
| FR-11 | AC-30, AC-34 |
| FR-12 | AC-08–AC-10, AC-27, AC-29–AC-31 |

See [Architecture](architecture.md), [Security model](security-model.md), and [Test strategy](test-strategy.md). Decisions are recorded in [ADR 0001](../adr/0001-build-purpose-built-plugin.md), [ADR 0002](../adr/0002-groups-in-single-option.md), [ADR 0003](../adr/0003-branch-editor-role-and-membership-capabilities.md), [ADR 0004](../adr/0004-additive-branch-authorization.md), [ADR 0005](../adr/0005-page-creation-via-add-subpage.md), [ADR 0006](../adr/0006-page-moves-admin-only.md), [ADR 0007](../adr/0007-deletion-admin-only.md), [ADR 0008](../adr/0008-layered-server-side-enforcement.md), [ADR 0010](../adr/0010-gutenberg-parent-ui-hiding.md), [ADR 0011](../adr/0011-membership-only-grants-page-access.md), [ADR 0012](../adr/0012-branch-root-slug-and-status-admin-only.md), [ADR 0013](../adr/0013-page-order-admin-only.md), [ADR 0014](../adr/0014-site-navigation-out-of-scope.md), [ADR 0015](../adr/0015-pages-list-limited-to-authorized-pages.md), [ADR 0016](../adr/0016-restricted-users-edit-own-uploads.md).
