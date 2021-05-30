=== OPcache Reset ===
Tags: PHP, Zend, OPcache, cache
Requires at least: 3.8
Tested up to: 5.0
Stable tag: 2.0.1
License: GPLv2 or later
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=FN6V2EZ7FSHXE

Automatic OPcache reset for WordPress

== Description ==

This plugin clears OPcache after updating Wordpress core, themes and files.
Unlike other plugins, it is also compatible with theme and plugin updates made by Wordpress Linux cron.

### Notice
* **Important**: To use this plugin, check the following.
	1. **PHP 5.5 or later**, Did you compile PHP with *--enable-opcache option*?
	2. **PHP 5.4 or earlier**, Did you installed *PECL ZendOpcache*?
	3. If not, please see [this document](http://php.net/book.opcache) and enable/install OPcache.
	4. The [cachetool](https://github.com/gordalina/cachetool) utility must be configured in order for OPCache to be cleared by this plugin. It must be in your `PATH` and named `cachetool`.

== Changelog ==

= 2.0.0 =
* Handle file OPCaches

= 1.0.0 =
* Initial release.
