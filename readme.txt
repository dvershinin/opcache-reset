=== OPcache Reset ===
Tags: PHP, Zend, OPcache, cache, WP-CLI
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.4.0
License: GPLv2 or later
Donate link: https://github.com/sponsors/dvershinin
Plugin URI: https://www.getpagespeed.com/wordpress-opcache-reset

Automatic OPcache reset for WordPress. Invalidates both in-memory and file-based OPCache upon upgrading WordPress.

== Description ==

This plugin clears OPcache after updating WordPress core, themes, and files.
Unlike other plugins, it is also compatible with [WordPress updates made by Linux cron](https://www.getpagespeed.com/server-setup/performance-friendly-wordpress-updates).

### Features

* Automatic OPcache reset after plugin/theme/core updates
* Clears both memory-based and file-based OPcache
* Works with cron-based WordPress updates (CLI context)
* WP-CLI commands for manual OPcache management
* Zero external dependencies (optional cachetool support)

### Configuration

The plugin works automatically for web-based updates. For CLI-based updates (cron, WP-CLI), configure your PHP-FPM socket:

**Option 1: Create `.cachetool.yml` in your WordPress root:**

    adapter: fastcgi
    fastcgi: /run/php-fpm/www.sock

**Option 2: Use cachetool binary** (if installed in PATH)

### WP-CLI Commands

Reset OPcache from the command line:

    wp opcache reset

Show OPcache status and statistics:

    wp opcache status

Output status as JSON:

    wp opcache status --format=json

== Changelog ==

= 2.4.0 =
* Added WP-CLI commands: `wp opcache reset` and `wp opcache status`
* Added direct FastCGI communication (no cachetool dependency required)
* Added support for cachetool.yml configuration format
* Added end-to-end FastCGI testing
* Improved test coverage (34 tests)

= 2.3.0 =
* Added comprehensive Docker-based integration test suite (27 tests)
* Added GitHub Actions CI for linting and testing
* Added PHPStan and PHPCS for code quality
* Improved OPcache reset verification with validate_timestamps=0 testing
* Added pre-commit hooks for automated code checks
* Updated plugin assets (icons, banners)

= 2.2.0 =
* Added support for file-based OPcache clearing with race condition handling
* Improved cachetool invocation with proper PATH handling

= 2.1.2 =
* No calling cachetool opcache:reset for setups with only file cache

= 2.1.0 =
* Handle file OPcaches in a consistent fashion

= 2.0.0 =
* Handle file OPCaches

= 1.0.0 =
* Initial release.
