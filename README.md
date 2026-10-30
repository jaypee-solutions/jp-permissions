# JP Permissions for WordPress

JP Permissions for WordPress lets an Administrator delegate parts of the page tree to Branch Editors while keeping control of site structure.

Administrators create branch groups, assign each one or more branch roots, and add members. A member's authorized pages are the branch roots and all pages below them. Membership only adds access: an Editor or Administrator remains unrestricted even when added to a branch group.

## Administrator setup

1. Activate the plugin. It registers the **Branch Editor** role for volunteers; this role can upload files but has no page access until assigned to a group.
2. Open **Users → Page Branches**, choose **Add group**, and give the branch group a name.
3. Choose a page as a branch root and select its members. Save the group. A save can add at most one root; edit the group again to add another. A page may be a root in more than one group, and nested roots are additive.
4. Assign volunteers the **Branch Editor** role under **Users → All Users**. Group membership controls which branches they can work in.

Only Administrators manage groups and site structure. Administrators can move and reorder pages, change branch-root slugs and status, and trash or delete pages. Trashing a root does not remove its descendants from the branch; permanently deleting a root removes that root from groups. Unpublish a page to take it offline without deleting it.

## Using the Branch Editor role

Branch Editors see their authorized pages in **Pages** and can edit, publish, schedule, unpublish, and preview them, including branch roots. Use **Add subpage** beside an authorized page to create a page beneath it. Branch Editors cannot move or reorder pages, change a branch root's slug or status, or trash, restore, or delete pages. Ask an Administrator to make structural changes; unpublish rather than delete.

The media library remains shared: Branch Editors can use shared images and edit their own uploads, but cannot edit others' uploads or delete media.

Group membership never reduces an Editor's or Administrator's access. Pages-list status counts remain site-wide, and branch-root slug/status controls are visible in the editor even though attempted changes are rejected.

## Navigation requirement

Page Branches does not manage site navigation. Configure the site to list branch pages automatically—for example, use the **Page List** block or a subpage listing on branch pages—so new pages created with Add subpage appear without an Administrator updating a menu. A hand-curated menu or Navigation block still requires an Administrator to add new pages (ADR 0014).

Scaffolded from [jaypee-solutions/wp-plugin-template](https://github.com/jaypee-solutions/wp-plugin-template).
Scaffolding changes (CI, scripts, hooks, lint/test config) arrive as pull requests
from the `template-update.yml` workflow, which runs `copier update` — edit them in
the template, not here.

## Requirements

- PHP 8.0+
- WordPress 7.1+
- Composer, Node 22.13+ (wp-env 12), and Docker or Podman (for `wp-env`)

## Development

```bash
composer install
npm install

composer lint          # PHPCS (WordPress Coding Standards)
composer lint:fix      # PHPCBF
composer test          # PHPUnit

npm run env:start      # wp-env at http://localhost:8888 (admin / password)
npm run test:e2e:run   # seed + Playwright
npm run env:stop
```

`.husky/pre-commit` runs `composer lint`, `.husky/pre-push` runs `composer test`.

### WordPress integration tests

Start the WordPress 7.1 wp-env tests environment, then run the shared integration
suite in that container:

```bash
WP_ENV_PORT=9020 WP_ENV_TESTS_PORT=9021 npm run env:start
npm run test:integration
WP_ENV_PORT=9020 WP_ENV_TESTS_PORT=9021 npm run env:stop
```

Integration tests use `WP_UnitTestCase` and the reusable `Branch_Fixtures`
trait in `tests/integration/class-branch-fixtures.php`. Add later ticket tests
under `tests/integration/` and use the trait; its pages/users/group option are
created and removed for each test. `create_fixture_user()` accepts role names,
including `jp_branch_editor` once that role is registered by the plugin.

## Release

1. Bump the version in **both** places in `jp-permissions.php` (the
   `Version:` header and the `VERSION` constant) — `scripts/check-version.sh`
   enforces that they agree — and in `readme.txt` (`Stable tag`).
2. Add the matching `## [X.Y.Z]` section to `CHANGELOG.md`.
3. Tag and push:

   ```bash
   git tag vX.Y.Z && git push --tags
   ```

`.github/workflows/release.yml` lints, tests, builds `build/jp-permissions-vX.Y.Z.zip`
and publishes it as a GitHub release asset. `scripts/build-release.sh` installs
the runtime dependencies with `--no-dev` and prefixes them with `php-scoper`, so
this plugin cannot collide with another JayPee plugin bundling a different
version of the same library on the same WordPress install.

## Updates

Sites pull updates from GitHub through the Plugin Update Checker. A private
repository needs a read-only token, pasted under
**Settings → JP Permissions for WordPress Updates**; the "Test token" button there says
exactly what GitHub answered when a check fails.
