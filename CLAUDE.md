# child-theme-maker: notes for Claude sessions

Free wordpress.org plugin (Appearance > Child Theme). Part of Enrique's plugin portfolio; project charter and queue live in the project's shared folder (`autopilot/`, `plans/`).

## Commands
See the table in README.md. Every gate before a PR is "ready for review": `composer lint`, `vendor/bin/phpcs`, `vendor/bin/phpunit`, `bin/build.sh` + Plugin Check on the built copy, `bin/e2e.sh` on 7.1.3 and on 6.3.

## Architecture
- `includes/class-ctmaker-generator.php`: pure string building (style.css, functions.php from `includes/templates/child-functions.php`, theme.json) and input validation. Unit tested.
- `includes/class-ctmaker-filesystem.php`: all writes go through WP_Filesystem (FTP/SSH hosts work); never write theme files with plain PHP functions.
- `includes/class-ctmaker-settings-copier.php`: theme_mods, Additional CSS (gets its own `custom_css` post), widgets, Site Editor posts (`wp_template`, `wp_template_part`, `wp_global_styles`, tagged by the `wp_theme` term).
- `includes/class-ctmaker-templates.php`: overridable file list; copies accept only paths from that list (path traversal guard) and never overwrite.
- `includes/class-ctmaker-admin.php`: the screen; forms post to the page itself so the filesystem credentials form can render; zips go through admin-post.php.
- The generated child theme must keep working without this plugin. Don't make it call plugin code.

## Conventions and traps
- Prefix: `ctmaker`/`CTMaker_`/`CTMAKER_` (WPCS rejects 3-letter prefixes).
- Plugin Check forbids heredoc, `suppress_filters => true`, and private core functions such as `wp_get_sidebars_widgets()`.
- WP_Query drops a `tax_query` when `name` is set (single-post lookup). Use `post_name__in`.
- Core de-duplicates `wp_template` slugs against the active theme while a new post has no `wp_theme` term yet; the copier re-saves the slug after setting the term.
- Text domain `child-theme-maker`; regenerate the .pot when strings change.
- Requires WP 6.3 (Site Editor theme preview link), PHP 7.4.
- The cloud sandbox can't reach wordpress.org downloads or Docker; `bin/e2e.sh` pulls WordPress, WP-CLI and the SQLite drop-in from GitHub instead.
