---
title: Test Strategy
aliases:
  - JP Permissions Test Strategy
tags:
  - prd
  - jp-permissions
status: draft
updated: 2026-10-28
---
# Test strategy

## Stack

Test at the highest seam: WordPress integration tests drive every server-side behavior through core entry points (capability checks, in-process REST requests, core post functions, admin form handlers, the Pages list query) as fixture users. The stub-based unit suite (`composer test`) covers only AC-34 (multisite activation refusal), because the wp-env test site is single-site; group-configuration normalization is observed through integration tests (AC-01–AC-03, AC-27). Playwright covers browser-only behavior (E2E-1…E2E-3). The WordPress integration suite uses `phpunit-integration.xml.dist`, `tests/integration/bootstrap.php`, wp-env `tests-cli`, and `yoast/phpunit-polyfills:^1.1`. Bootstrap requires Composer autoload, defines `WP_TESTS_PHPUNIT_POLYFILLS_PATH`, loads the plugin via `tests_add_filter('muplugins_loaded', …)`, then includes `getenv('WP_TESTS_DIR') . '/includes/bootstrap.php'`.

```sh
npx wp-env run tests-cli --env-cwd=wp-content/plugins/jp-permissions vendor/bin/phpunit -c phpunit-integration.xml.dist
```

Expose this as `test:integration`. If the WP test library fails with PHPUnit 10.5, pin `phpunit/phpunit:^9.6` repository-wide (the unit tests are 9.6-compatible) and record the contingency under [ADR 0009](../adr/0009-integration-test-stack.md). PHPUnit 10.5 compatibility is UNVERIFIED.

## Fixtures

`tests/integration/class-branch-fixtures.php` defines a shared fixture trait.

| Fixture type | Data |
|---|---|
| Pages | Home; Membership; Football → Teams → Youth and Adults; Football → Training; Tennis → Courts and Training; Swimming → Courses |
| Groups | G1 Football Editors: Football; G2 Tennis Editors: Tennis; G3 Swimming Editors: Swimming; G4 Youth Coaches: Youth |
| Users | `admin` administrator; `staff` editor/no group; `fb` Branch Editor/G1; `multi` Branch Editor/G2+G3; `youth` Branch Editor/G4; `fb_staff` editor/G1; `orphan` Branch Editor/no group; `sub` subscriber |
| Orphan redirect | `orphan` is a Branch Editor with no group and is not restricted; core denies `post-new.php?post_type=page` for lack of `edit_pages`, with no plugin redirect. |

## Authorization matrix

Legend: ✅ operation succeeds; ❌ operation is denied (or the specified no-op/redirect outcome). Method names follow `test_<operation>_<user>`; methods may use explicit fixture-page suffixes where one user has different expected outcomes across pages.

| Operation / test method | admin | staff | fb | multi | youth | fb_staff | orphan | sub |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Edit Football (`test_edit_football_<user>`) | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Edit Youth (`test_edit_youth_<user>`) | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ |
| Edit Tennis (`test_edit_tennis_<user>`) | ✅ | ✅ | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Edit Home (`test_edit_home_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Publish authorized page (`test_publish_authorized_<user>`) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Read draft in other branch (`test_read_other_branch_draft_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Pages list includes Tennis (`test_pages_list_includes_tennis_<user>`) | ✅ | ✅ | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ |
| REST create under Training (`test_rest_create_training_<user>`) | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| REST create under Football (`test_rest_create_football_<user>`) | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| REST create at root (`test_rest_create_root_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| REST create under Tennis (`test_rest_create_tennis_<user>`) | ✅ | ✅ | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ |
| REST update parent change (`test_rest_change_parent_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| REST update parent omitted/unchanged (`test_rest_unchanged_parent_<user>`) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Autosave auto-draft retaining parent (`test_autosave_autodraft_<user>`) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Classic edit with changed `parent_id` (`test_classic_change_parent_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Quick Edit changed `post_parent` (`test_quick_edit_change_parent_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Bulk edit changed parent (`test_bulk_edit_change_parent_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Root slug/status change (`test_root_slug_and_status_locked_for_restricted`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Menu order change (`test_menu_order_change_denied_restricted`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Trash / untrash / delete (`test_delete_any_page_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| REST DELETE (`test_rest_delete_page_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Add subpage valid nonce/authorized parent (`test_add_subpage_valid_<user>`) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Add subpage invalid nonce (`test_add_subpage_invalid_nonce_<user>`) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Add subpage unauthorized parent (`test_add_subpage_foreign_parent_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| `post-new.php` redirect (`test_post_new_redirect_<user>`) | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| System-context programmatic insert (`test_system_context_insert_<user>`) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Admin root delete removes root (`test_admin_delete_root_cleanup_<user>`) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| User delete removes membership (`test_delete_user_cleanup_<user>`) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Corrupt config grants no branch access (`test_corrupt_option_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Hierarchy cycle terminates and denies (`test_hierarchy_cycle_<user>`) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |

The matrix rows describe representative expectations; in tests, invoke each operation against the specifically named fixture. Group administration, root validation, and lifecycle are covered by the acceptance mapping below and dedicated test cases.

## Acceptance criteria → test mapping

| AC | Integration test name (at least one per criterion) |
|---|---|
| AC-01 | `test_group_crud_admin_only`; `test_group_form_nonce_required` |
| AC-02 | `test_group_name_validation_errors` |
| AC-03 | `test_root_validation_drops_invalid_ids` |
| AC-04 | `test_edit_youth_fb` |
| AC-05 | `test_cross_branch_post_and_rest_denied` |
| AC-06 | `test_nested_roots_additive` |
| AC-07 | `test_multi_group_union` |
| AC-08 | `test_editor_member_unrestricted` |
| AC-09 | `test_administrator_unrestricted` |
| AC-10 | `test_orphan_has_no_page_access` |
| AC-11 | `test_add_subpage_creates_autodraft_and_redirects` |
| AC-12 | `test_add_subpage_invalid_parent_or_nonce_denied` |
| AC-13 | `test_post_new_redirect_restricted` |
| AC-14 | `test_rest_create_root_or_foreign_parent_forbidden` |
| AC-15 | `test_rest_create_authorized_parent_allowed` |
| AC-16 | `test_parent_change_denied_rest_classic_quick_bulk` |
| AC-17 | `test_parent_omitted_unchanged_and_autosave_allowed` |
| AC-18 | `test_authorized_page_publish_schedule_private` |
| AC-19 | `test_unauthorized_page_publish_denied` |
| AC-20 | `test_revisions_list_restore_preview_authorized_and_denied` |
| AC-21 | `test_trash_untrash_delete_denied_all_paths` |
| AC-22 | `test_unpublish_descendant_allowed_root_status_denied` |
| AC-23 | `test_admin_permanent_root_delete_cleans_roots` |
| AC-24 | `test_deleted_user_cleans_membership` |
| AC-25 | `test_admin_trash_root_keeps_descendants_and_delete_reparents` |
| AC-26 | `test_gutenberg_parent_panel_hidden_for_restricted_only`; `E2E-3` |
| AC-27 | `test_corrupt_or_missing_option_fails_closed` |
| AC-28 | `test_hierarchy_cycle_terminates_and_denies` |
| AC-29 | `test_system_context_unrestricted` |
| AC-30 | `test_activation_deactivation_uninstall_lifecycle` |
| AC-31 | `test_root_slug_and_status_locked_for_restricted` |
| AC-32 | `test_menu_order_change_denied_restricted` |
| AC-33 | `test_pages_list_authorized_only` |
| AC-34 | `test_activation_refused_on_multisite` (stub-based unit test; the wp-env integration site is single-site) |
| AC-35 | `test_restricted_user_edits_own_attachment_only` |

E2E identifiers are E2E-1…E2E-3 as listed below.

## End-to-end and CI

Use plain `@playwright/test` (not `@wordpress/e2e-test-utils-playwright`). `tests/seed.php` is idempotent, creates fixture pages/users with password `password`, and writes the group option.

| ID | Flow |
|---|---|
| E2E-1 | `fb` adds a subpage under Training; editor opens; enters title and publishes; verify published status and Training parent. |
| E2E-2 | `fb` opens the Tennis edit URL and sees “Sorry, you are not allowed to edit this item.” |
| E2E-3 | `fb` has no parent control in the editor while `admin` does; if WP 7.x still shows an editable parent field, changing it must yield the 403 error notice and a note is recorded under ADR 0010. |

Add repo-local `.github/workflows/integration.yml` on push/PR to `main` (B-14, release gate), leaving template-owned `ci.yml` untouched: checkout; setup Node 20 and PHP 8.2; `composer install`; `npm ci`; `npx wp-env start`; run the integration command above. B-13 (post-release) adds `npm run test:e2e:run` as a final step. `composer lint` must pass.
