<?php
/**
 * Plugin Name: OPcache Reset
 * Plugin URI: http://wordpress.org/plugins/opcache-reset/
 * Description: Automatic reset of OPcache
 * Version: 2.4.1
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
		$reset = opcache_reset();
		if ( ! $reset && function_exists( 'opcache_get_status' ) ) {
			$opcache_status = opcache_get_status( false );
			$reset          = is_array( $opcache_status ) && ( ! empty( $opcache_status['restart_pending'] ) || ! empty( $opcache_status['restart_in_progress'] ) );
		}
		echo $reset ? 'OK' : 'RESET_FAILED';
	} else {
		echo 'NO_OPCACHE';
	}
	exit;
}

// Prevent direct access.
//
// This MUST stay below the FastCGI handler above: that handler is deliberately
// reachable with WordPress not loaded, which is the entire point of the direct
// FastCGI reset path. Everything from here down requires a booted WordPress.
if ( ! defined( 'ABSPATH' ) ) {
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
 * Recursively remove an OPcache file-cache directory.
 *
 * The cache lives outside the WordPress filesystem and must be managed without
 * WP_Filesystem so resets also work when shell functions are disabled.
 *
 * @param string $directory Directory to remove.
 * @return bool Whether the directory was removed or no longer exists.
 */
function gps_opcache_remove_directory( $directory ) {
	if ( ! is_dir( $directory ) ) {
		return ! file_exists( $directory );
	}

	try {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
	} catch ( UnexpectedValueException $exception ) {
		return false;
	}

	foreach ( $iterator as $item ) {
		$path = $item->getPathname();
		if ( $item->isDir() && ! $item->isLink() ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup; the result is checked below.
			if ( ! @rmdir( $path ) && is_dir( $path ) ) {
				return false;
			}
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup; the result is checked below.
		} elseif ( ! @unlink( $path ) && file_exists( $path ) ) {
			return false;
		}
	}

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup; the result is checked below.
	return @rmdir( $directory ) || ! is_dir( $directory );
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

	$retired_cache_dir = $file_cache_dir ? "{$file_cache_dir}.rm" : null;
	if ( $retired_cache_dir && file_exists( $retired_cache_dir ) ) {
		gps_opcache_remove_directory( $retired_cache_dir );
	}

	if ( $file_cache_dir && $retired_cache_dir && file_exists( $file_cache_dir ) ) {
		// move it out of the way to avoid race conditions
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cross-device failure has an in-place fallback below.
		$cache_rotated = @rename( $file_cache_dir, $retired_cache_dir );
		if ( ! $cache_rotated ) {
			// Overlay/container filesystems can reject directory renames with EXDEV.
			gps_opcache_remove_directory( $file_cache_dir );
		}
	}

	// We are in PHP-FPM context and not file cache only
	if ( php_sapi_name() !== 'cli' && ! ini_get( 'opcache.file_cache_only' ) ) {
		opcache_reset();
	}

	if ( $file_cache_dir ) {
		if ( $retired_cache_dir && file_exists( $retired_cache_dir ) ) {
			gps_opcache_remove_directory( $retired_cache_dir );
		}
		// make sure OPcache directory is re-created
		if ( ! file_exists( $file_cache_dir ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Another worker may recreate it concurrently.
			@mkdir( $file_cache_dir, 0777, true );
		}
	}

	// A CLI process cannot reset PHP-FPM's shared-memory cache directly, so send
	// the reset to FPM over FastCGI using the socket from .cachetool.yml.
	require_once __DIR__ . '/opcache-fastcgi.php';
	$config_path = gps_find_cachetool_config();
	if ( $config_path ) {
		$config = gps_parse_cachetool_yml( $config_path );
		if ( $config ) {
			gps_fastcgi_opcache_reset( $config['fastcgi'], $config['fastcgi_chroot'] );
		}
	}
}


// Reset OPcache after plugin/theme/core updates (files are modified on disk).
// Note: Activation/deactivation/theme-switch don't need cache clear since files aren't modified.
add_action( 'upgrader_process_complete', 'gps_opcache_reset', PHP_INT_MAX - 1, 2 );

// Reset OPcache after a plugin is deleted/uninstalled (files are removed from disk).
// Important when opcache.validate_timestamps=0 to prevent serving cached deleted files.
add_action( 'deleted_plugin', 'gps_opcache_reset', PHP_INT_MAX - 1, 2 );
