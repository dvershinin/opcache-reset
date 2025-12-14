<?php
/**
 * Plugin Name: Dummy Test Plugin
 * Description: A test plugin that registers a REST endpoint for OPcache testing
 * Version: 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This value will be changed during tests to verify OPcache behavior.
define( 'DUMMY_PLUGIN_BUILD_ID', 'BUILD_V1_ORIGINAL' );

/**
 * Register REST API endpoint that returns the build ID.
 * If OPcache serves stale bytecode, this will return the OLD value
 * even after the file is updated on disk.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'dummy/v1',
			'/info',
			array(
				'methods'             => 'GET',
				'callback'            => 'dummy_plugin_get_info',
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * Return plugin info including build ID.
 *
 * @return WP_REST_Response
 */
function dummy_plugin_get_info() {
	return new WP_REST_Response(
		array(
			'build_id'   => DUMMY_PLUGIN_BUILD_ID,
			'version'    => '1.0.0',
			'file'       => __FILE__,
			'file_mtime' => filemtime( __FILE__ ),
		),
		200
	);
}
