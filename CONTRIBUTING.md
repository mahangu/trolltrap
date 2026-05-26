# Contributing to Troll Trap

Thanks for taking the time to contribute. This document explains how the project is laid out and what the development loop looks like.

## Repository layout

```
index.php                            Plugin entrypoint, header, version constant.
includes/
  class-trolltrap.php                Main class, bootstrap, comment_post + comment_text hooks.
  class-trolltrap-filters.php        Filter registry (transforming, enabled, apply).
  class-trolltrap-convert.php        Built-in transforms (piglatin, zalgo, uwu, ...).
  class-trolltrap-ai.php             Optional Anthropic-backed AI rewrite.
  class-trolltrap-settings.php       Settings > Discussion UI, bulk actions, admin column, dashboard widget.
  class-trolltrap-cli.php            WP-CLI commands (loaded only when WP_CLI is defined).
uninstall.php                        Removes options and comment meta on plugin deletion.
readme.txt                           WordPress.org plugin readme (Stable tag, Changelog).
README.md                            GitHub README.
phpcs.xml.dist                       WPCS configuration.
.github/
  workflows/ci.yml                   PHP lint matrix, WordPress Coding Standards, Playground integration.
  workflows/release.yml              Build and publish the distribution zip.
  playground/blueprint.json          Playground integration-test steps.
.wordpress-org/                      Screenshots served by the plugin directory.
.distignore                          Files excluded from the distribution zip.
```

## Local development

1. Clone the repo into a fresh WordPress install:

   ```
   git clone https://github.com/mahangu/troll-trap.git wp-content/plugins/troll-trap
   ```

2. Activate the plugin from the WordPress admin **Plugins** screen, or via WP-CLI:

   ```
   wp plugin activate troll-trap
   ```

3. Configure the plugin under **Settings > Discussion > Troll Trap**.

The plugin has no build step, no Composer install, no Node dependencies; the source you check out is the source that runs.

## Running the checks locally

The CI matrix in `.github/workflows/ci.yml` is the source of truth, but the same checks run cleanly from a local shell.

### PHP syntax (lints every PHP file)

```
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

### WordPress Coding Standards

```
composer global require --no-interaction --no-progress \
  squizlabs/php_codesniffer \
  wp-coding-standards/wpcs \
  phpcsstandards/phpcsutils \
  phpcsstandards/phpcsextra \
  dealerdirect/phpcodesniffer-composer-installer
phpcs --report-full
```

### Playground integration tests

Run the same blueprint CI runs:

```
npx --yes @wp-playground/cli@latest run-blueprint \
  --blueprint=.github/playground/blueprint.json \
  --wp=latest --php=8.2 \
  --mount="$PWD:/wordpress/wp-content/plugins/troll-trap"
```

Each `runPHP` step in the blueprint is an integration test: insert a comment, exercise a hook, assert the resulting state. Add steps when you add features; failures print a `FAIL: …` line to stdout and exit non-zero.

## Coding conventions

- WordPress Coding Standards (PHPCS rules in `phpcs.xml.dist`). The CI run is authoritative.
- All user-facing strings use the `troll-trap` text domain via `__()`, `_n()`, `esc_html__()`, or `esc_attr__()`.
- Every output goes through an appropriate escaping function. New code that prints unescaped output will fail review.
- The plugin's prefix is `trolltrap` for functions/options/meta and `Mahangu_Troll_Trap` for classes. The PHPCS `PrefixAllGlobals` rule enforces this.
- Direct `$wpdb` queries are allowed where the WP_Query API can't express what we need (the CLI dry-runs and the stats widget), but use `$wpdb->prepare()` and add a brief comment explaining the choice.

## Pull request workflow

1. Open a branch named after the change (`feat/...`, `fix/...`, `docs/...`, `ci/...`, `chore/...`).
2. Keep PRs focused. A typical PR touches one feature plus its Playground test plus any uninstall/changelog updates required.
3. Include a `Test plan:` section in the description listing the manual or automated checks done.
4. CI must be green before merge: PHP lint matrix, WPCS, all Playground matrix cells.
5. Squash on merge so master history reads as one logical change per PR.

## Releasing

Releases are cut by bumping three locations in lockstep:

- `Version:` header in `index.php`
- `TROLLTRAP_VERSION` constant in `index.php`
- `Stable tag:` in `readme.txt`

The matching `== Changelog ==` entry goes in `readme.txt`. The release workflow at `.github/workflows/release.yml` produces the distribution zip.
