<?php
/*
 * Plugin Name: OPcache Reset
 * Plugin URI: http://wordpress.org/plugins/opcache-reset/
 * Description: Automatic reset of OPcache
 * Version: 2.1.6
 * Author: Danila Vershinin
 * Author URI: https://www.getpagespeed.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

if ( is_admin() ) {
    if (! (defined( 'DOING_AJAX' ) && DOING_AJAX)) {
        // We are in admin mode: notices and such are handled here
        require_once(__DIR__ . '/opcache-admin.php');
    }
}

/**
 * Reset OPCache using different approach depending on the caller context (e.g. cron vs. web)
 */
function gps_opcache_reset() {

    if( ! function_exists('opcache_reset') ) {
        // Bail if extension is not loaded
        return;
    }


    if (empty( ini_get( 'opcache.enable' ))) {
        // Do not try doing anything if OPcache is loaded but disabled
        return;
    }

    if ( ! empty( ini_get( 'opcache.restrict_api' ) ) && strpos( __FILE__, ini_get( 'opcache.restrict_api' ) ) !== 0 ) {
        return;
    }

    // https://www.getpagespeed.com/server-setup/php/zend-opcache
    // Follow the principles of reliable file cache clearing from this article
    $fileCacheDir = ini_get( 'opcache.file_cache' );
    // Check if file cache is enabled and delete it if enabled
    if ( $fileCacheDir && is_writable( $fileCacheDir ) ) {
        // check if we can create subdirectory in the parent directory.
        // Normally, it's ~/.cache so we can
        $cacheDir = dirname($fileCacheDir);
        if (! is_writable($cacheDir)) {
            $fileCacheDir = null;
        }
    }

    if ($fileCacheDir && file_exists($fileCacheDir)) {
        // move it out of the way to avoid race conditions
        shell_exec("mv ${fileCacheDir} ${fileCacheDir}.rm");
    }

    // We are in PHP-FPM context and not file cache only
    if (php_sapi_name() !== 'cli' && ! ini_get( 'opcache.file_cache_only' )) {
        opcache_reset();
    }

    if ($fileCacheDir) {
        // if file cache only, do not use opcache:reset as it cannot clear file caches and yield error in such case
        if (! ini_get( 'opcache.file_cache_only' )) {
            shell_exec('php -d opcache.enable_cli=0 -d opcache.file_cache=/tmp $(which cachetool) opcache:reset');
        }
        if ( file_exists( "${fileCacheDir}.rm" )) {
            shell_exec( "rm -rf ${fileCacheDir}.rm" );
        }
        // make sure OPcache directory is re-created
        shell_exec( "mkdir -p ${fileCacheDir}" );
    }

}


add_action( 'upgrader_process_complete', 'gps_opcache_reset', PHP_INT_MAX - 1, 2 );



