# Child Theme Maker

Free WordPress plugin: create a safe child theme for any classic or block theme in three steps, carry over Customizer and Site Editor settings, override parent templates, and download the child as a .zip. No external services, no front-end code.

The wordpress.org listing text lives in [readme.txt](readme.txt).

## Development

| Job | Command |
|---|---|
| Install dev tools | `composer install` |
| Lint (PHP syntax) | `composer lint` |
| Coding standards (WPCS + PHP 7.4 compatibility) | `vendor/bin/phpcs` |
| Unit tests | `vendor/bin/phpunit` |
| Build the release copy and zip | `bin/build.sh` → `build/child-theme-maker/`, `build/child-theme-maker.zip` |
| Browser test on real WordPress (SQLite) | `bin/e2e.sh` (`WP_VERSION=6.3 BLOCK_PARENT=twentytwentythree bin/e2e.sh` for the oldest supported) |
| Plugin Check | `wp plugin check child-theme-maker --include-experimental` on a site with the built copy installed |
| Translation template | `wp i18n make-pot . languages/child-theme-maker.pot --exclude=vendor,build,tests,bin` |

CI (`.github/workflows/ci.yml`) runs all of the above on pull requests and on `main`.

`.wordpress-org/` holds the directory screenshots; they go to the SVN `assets/` folder, not into the plugin zip.

## License

GPL-2.0-or-later.
