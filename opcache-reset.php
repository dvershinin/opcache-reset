<?php
/*
 * Plugin Name: OPcache Reset
 * Plugin URI: http://wordpress.org/plugins/opcache-reset/
 * Description: Automatic reset of OPcache
 * Version: 2.0.1
 * Author: Danila Vershinin
 * Author URI: https://www.smartycode.com/
 * License: GPLv2 or later
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

/**
 * Reset OPCache using different approach depending on the caller context (e.g. cron vs. web)
 */
function gps_opcache_reset() {

    if( ! function_exists('opcache_reset') ) {
        return;
    }

    if ( ! empty( ini_get( 'opcache.restrict_api' ) ) && strpos( __FILE__, ini_get( 'opcache.restrict_api' ) ) !== 0 ) {
        return;
    }

    // Check if file cache is enabled and delete it if enabled
    if ( ini_get( 'opcache.file_cache' ) && is_writable( ini_get( 'opcache.file_cache' ) ) ) {
        $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ini_get('opcache.file_cache'), RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $files as $fileinfo ) {
            $todo = ( $fileinfo->isDir() ? 'rmdir' : 'unlink' );
            $todo( $fileinfo->getRealPath() );
        }
    }

    if (! ini_get( 'opcache.file_cache_only' )) {
        if (php_sapi_name() !== 'cli') {
            opcache_reset();
        } else {
            shell_exec( 'cachetool opcache:reset' );
        }
    }
}


add_action( 'upgrader_process_complete', 'gps_opcache_reset', PHP_INT_MAX - 1, 2 );

