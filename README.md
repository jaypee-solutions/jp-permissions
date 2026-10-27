# JP Permissions for WordPress

JP Permissions for WordPress is a WordPress plugin.

Scaffolded from [jaypee-solutions/wp-plugin-template](https://github.com/jaypee-solutions/wp-plugin-template).
Scaffolding changes (CI, scripts, hooks, lint/test config) arrive as Renovate
PRs against `.copier-answers.yml` and are applied with `copier update` — edit
them in the template, not here.

## Requirements

- PHP 8.0+
- WordPress 6.4+
- Composer, Node 20+, and Docker or Podman (for `wp-env`)

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
# jp-permissions
