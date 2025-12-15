<?php
/**
 * Plugin Name: OPcache Reset
 * Plugin URI: http://wordpress.org/plugins/opcache-reset/
 * Description: Automatic reset of OPcache
 * Version: 2.4.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Danila Vershinin
 * Author URI: https://www.getpagespeed.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Handle direct FastCGI OPcache reset request.
// This param can ONLY be set via direct FastCGI connection to PHP-FPM.
// nginx/Apache never forward arbitrary custom CGI params - only HTTP_* headers.
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Checking existence only.
if ( isset( $_SERVER['GPS_OPCACHE_RESET_INTERNAL'] ) && '1' === $_SERVER['GPS_OPCACHE_RESET_INTERNAL'] ) {
	if ( function_exists( 'opcache_reset' ) ) {
		opcache_reset();
		echo 'OK';
	} else {
		echo 'NO_OPCACHE';
	}
	exit;
}

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

if ( is_admin() ) {
	if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		// We are in admin mode: notices and such are handled here
		require_once __DIR__ . '/opcache-admin.php';
	}
}

// Load WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/opcache-cli.php';
}

/**
 * Reset OPCache using different approach depending on the caller context (e.g. cron vs. web)
 */
function gps_opcache_reset() {

	if ( ! function_exists( 'opcache_reset' ) ) {
		// Bail if extension is not loaded
		return;
	}

	if ( empty( ini_get( 'opcache.enable' ) ) ) {
		// Do not try doing anything if OPcache is loaded but disabled
		return;
	}

	if ( ! empty( ini_get( 'opcache.restrict_api' ) ) && strpos( __FILE__, ini_get( 'opcache.restrict_api' ) ) !== 0 ) {
		return;
	}

	// https://www.getpagespeed.com/server-setup/php/zend-opcache
	// Follow the principles of reliable file cache clearing from this article
	$file_cache_dir = ini_get( 'opcache.file_cache' );
	// Check if file cache is enabled and delete it if enabled
	if ( $file_cache_dir && is_writable( $file_cache_dir ) ) {
		// check if we can create subdirectory in the parent directory.
		// Normally, it's ~/.cache so we can
		$cache_dir = dirname( $file_cache_dir );
		if ( ! is_writable( $cache_dir ) ) {
			$file_cache_dir = null;
		}
	}

	if ( $file_cache_dir && file_exists( $file_cache_dir ) ) {
		// move it out of the way to avoid race conditions
		shell_exec( "mv {$file_cache_dir} {$file_cache_dir}.rm" );
	}

	// We are in PHP-FPM context and not file cache only
	if ( php_sapi_name() !== 'cli' && ! ini_get( 'opcache.file_cache_only' ) ) {
		opcache_reset();
	}

	if ( $file_cache_dir ) {
		if ( file_exists( "{$file_cache_dir}.rm" ) ) {
			shell_exec( "rm -rf {$file_cache_dir}.rm" );
		}
		// make sure OPcache directory is re-created
		shell_exec( "mkdir -p {$file_cache_dir}" );
	}

	// Irrespective of the context, we should attempt to clear the memory-based OPCache, because this script may be called from CLI
	// and can't be aware of whether PHP-FPM is running with file_cache_only => On
	// Since the script may be called via cron or PHP-FPM with clear_env settings, the PATH may not be set properly, so we need to set it
	$username    = getenv( 'USER' );
	$path        = "/home/{$username}/.local/bin:/usr/bin:/bin";
	$system_path = getenv( 'PATH' );
	if ( $system_path ) {
		$path .= ':' . $system_path;
	}

	// Try cachetool binary first.
	$cachetool = trim( shell_exec( "PATH={$path} which cachetool 2>/dev/null" ) );
	if ( $cachetool ) {
		$cmd = 'php -d opcache.enable_cli=0 -d opcache.file_cache_only=0 -d opcache.file_cache=/tmp $(which cachetool) opcache:reset';
		shell_exec( "PATH={$path} {$cmd} 2>&1" );
		return;
	}

	// Fallback: direct FastCGI if cachetool.yml exists.
	require_once __DIR__ . '/opcache-fastcgi.php';
	$config_path = gps_find_cachetool_config();
	if ( $config_path ) {
		$socket = gps_parse_cachetool_yml( $config_path );
		if ( $socket ) {
			gps_fastcgi_opcache_reset( $socket );
		}
	}
}


// Reset OPcache after plugin/theme/core updates (files are modified on disk).
// Note: Activation/deactivation/theme-switch don't need cache clear since files aren't modified.
add_action( 'upgrader_process_complete', 'gps_opcache_reset', PHP_INT_MAX - 1, 2 );

// Reset OPcache after a plugin is deleted/uninstalled (files are removed from disk).
// Important when opcache.validate_timestamps=0 to prevent serving cached deleted files.
add_action( 'deleted_plugin', 'gps_opcache_reset', PHP_INT_MAX - 1, 2 );
