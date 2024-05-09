=== OPcache Reset ===
Tags: PHP, Zend, OPcache, cache
Requires at least: 3.8
Tested up to: 6.5
Stable tag: 2.2.0
License: GPLv2 or later
Donate link: https://github.com/sponsors/dvershinin
Plugin URI: https://www.getpagespeed.com/wordpress-opcache-reset

Automatic OPcache reset for WordPress. Invalidates both in-memory and file-based OPCache upon upgrading WordPress.

== Description ==

This plugin clears OPcache after updating WordPress core, themes, and files.
Unlike other plugins, it is also compatible with [WordPress updates made by Linux cron](https://www.getpagespeed.com/server-setup/performance-friendly-wordpress-updates).

### Notice
* **Important**: To use this plugin, check the following.
	1. **PHP 5.5 or later**, Did you compile PHP with *--enable-opcache option*?
	2. **PHP 5.4 or earlier**, Did you installed *PECL ZendOpcache*?
	3. If not, please see [this document](http://php.net/book.opcache) and enable/install OPcache.
	4. The [cachetool](https://github.com/gordalina/cachetool) utility must be configured in order for OPCache to be cleared by this plugin. It must be in your `PATH` and named `cachetool`.
	5. The "which" utility (typically preinstalled), thus a Linux OS

== Changelog ==

= 2.1.2 =
* No calling cachetool opcache:reset for setups with only file cache

= 2.1.0 =
* Handle file OPcaches in a consistent fashion

= 2.0.0 =
* Handle file OPCaches

= 1.0.0 =
* Initial release.
