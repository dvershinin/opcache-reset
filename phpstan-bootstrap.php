<?php
/**
 * PHPStan bootstrap file.
 *
 * Defines WordPress constants and stubs that PHPStan needs
 * to analyze WordPress plugin code.
 *
 * @package opcache-reset
 */

// Define ABSPATH if not already defined.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/var/www/html/' );
}

// Common WordPress constants.
define( 'WPINC', 'wp-includes' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
