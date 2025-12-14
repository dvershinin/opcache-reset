<?php
/**
 * Test Control Plugin
 *
 * Provides REST API endpoints for integration testing of the OPcache Reset plugin.
 *
 * @package opcache-reset-tests
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register test REST API endpoints.
 */
add_action(
	'rest_api_init',
	function () {
		// Get OPcache status.
		register_rest_route(
			'test/v1',
			'/opcache-status',
			array(
				'methods'             => 'GET',
				'callback'            => 'opcache_test_get_status',
				'permission_callback' => '__return_true',
			)
		);

		// Trigger OPcache reset.
		register_rest_route(
			'test/v1',
			'/opcache-reset',
			array(
				'methods'             => 'POST',
				'callback'            => 'opcache_test_trigger_reset',
				'permission_callback' => '__return_true',
			)
		);

		// Create a test post.
		register_rest_route(
			'test/v1',
			'/post',
			array(
				'methods'             => 'POST',
				'callback'            => 'opcache_test_create_post',
				'permission_callback' => '__return_true',
			)
		);

		// Simulate plugin update (triggers upgrader_process_complete hook).
		register_rest_route(
			'test/v1',
			'/simulate-update',
			array(
				'methods'             => 'POST',
				'callback'            => 'opcache_test_simulate_update',
				'permission_callback' => '__return_true',
			)
		);

		// Get file cache directory info.
		register_rest_route(
			'test/v1',
			'/file-cache-info',
			array(
				'methods'             => 'GET',
				'callback'            => 'opcache_test_file_cache_info',
				'permission_callback' => '__return_true',
			)
		);

		// Simulate plugin deletion (triggers deleted_plugin hook).
		register_rest_route(
			'test/v1',
			'/simulate-plugin-delete',
			array(
				'methods'             => 'POST',
				'callback'            => 'opcache_test_simulate_plugin_delete',
				'permission_callback' => '__return_true',
			)
		);

		// Install the dummy test plugin.
		register_rest_route(
			'test/v1',
			'/dummy-plugin/install',
			array(
				'methods'             => 'POST',
				'callback'            => 'opcache_test_dummy_plugin_install',
				'permission_callback' => '__return_true',
			)
		);

		// Get the dummy plugin's build ID (to verify cache state).
		register_rest_route(
			'test/v1',
			'/dummy-plugin/build-id',
			array(
				'methods'             => 'GET',
				'callback'            => 'opcache_test_dummy_plugin_build_id',
				'permission_callback' => '__return_true',
			)
		);

		// Update the dummy plugin (change build ID).
		register_rest_route(
			'test/v1',
			'/dummy-plugin/update',
			array(
				'methods'             => 'POST',
				'callback'            => 'opcache_test_dummy_plugin_update',
				'permission_callback' => '__return_true',
			)
		);

		// Uninstall the dummy plugin (via PHP delete_plugins).
		register_rest_route(
			'test/v1',
			'/dummy-plugin/uninstall',
			array(
				'methods'             => 'POST',
				'callback'            => 'opcache_test_dummy_plugin_uninstall',
				'permission_callback' => '__return_true',
			)
		);

		// Cleanup dummy plugin files (no hooks, for test cleanup only).
		register_rest_route(
			'test/v1',
			'/dummy-plugin/cleanup',
			array(
				'methods'             => 'POST',
				'callback'            => 'opcache_test_dummy_plugin_cleanup',
				'permission_callback' => '__return_true',
			)
		);

		// Delete dummy plugin via wp-cli (tests CLI context OPcache clearing).
		register_rest_route(
			'test/v1',
			'/dummy-plugin/delete-via-wpcli',
			array(
				'methods'             => 'POST',
				'callback'            => 'opcache_test_dummy_plugin_delete_wpcli',
				'permission_callback' => '__return_true',
			)
		);

		// Activate dummy plugin via wp-cli.
		register_rest_route(
			'test/v1',
			'/dummy-plugin/activate-wpcli',
			array(
				'methods'             => 'POST',
				'callback'            => 'opcache_test_dummy_plugin_activate_wpcli',
				'permission_callback' => '__return_true',
			)
		);

		// Check if a specific file is cached in OPcache.
		register_rest_route(
			'test/v1',
			'/opcache-file-status',
			array(
				'methods'             => 'GET',
				'callback'            => 'opcache_test_file_cache_status',
				'permission_callback' => '__return_true',
			)
		);

		// Pure PHP OPcache test (bypasses WordPress plugin system).
		register_rest_route(
			'test/v1',
			'/pure-opcache-test',
			array(
				'methods'             => 'POST',
				'callback'            => 'opcache_test_pure_php',
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * Get OPcache status.
 *
 * @return WP_REST_Response
 */
function opcache_test_get_status() {
	$status = array(
		'extension_loaded' => extension_loaded( 'Zend OPcache' ),
		'enabled'          => ! empty( ini_get( 'opcache.enable' ) ),
		'cli_enabled'      => ! empty( ini_get( 'opcache.enable_cli' ) ),
		'file_cache'       => ini_get( 'opcache.file_cache' ),
		'file_cache_only'  => ! empty( ini_get( 'opcache.file_cache_only' ) ),
		'restrict_api'     => ini_get( 'opcache.restrict_api' ),
	);

	if ( function_exists( 'opcache_get_status' ) ) {
		$opcache_status = opcache_get_status( false );
		if ( is_array( $opcache_status ) ) {
			$status['opcache_enabled']    = $opcache_status['opcache_enabled'] ?? false;
			$status['cache_full']         = $opcache_status['cache_full'] ?? false;
			$status['memory_usage']       = $opcache_status['memory_usage'] ?? array();
			$status['opcache_statistics'] = $opcache_status['opcache_statistics'] ?? array();
		}
	}

	return new WP_REST_Response( $status, 200 );
}

/**
 * Trigger OPcache reset via the plugin function.
 *
 * @return WP_REST_Response
 */
function opcache_test_trigger_reset() {
	$before_stats = null;
	$after_stats  = null;

	// Get stats before reset.
	if ( function_exists( 'opcache_get_status' ) ) {
		$status       = opcache_get_status( false );
		$before_stats = $status['opcache_statistics'] ?? null;
	}

	// Call the plugin's reset function if it exists.
	if ( function_exists( 'gps_opcache_reset' ) ) {
		gps_opcache_reset();
	}

	// Get stats after reset.
	if ( function_exists( 'opcache_get_status' ) ) {
		$status      = opcache_get_status( false );
		$after_stats = $status['opcache_statistics'] ?? null;
	}

	return new WP_REST_Response(
		array(
			'success'      => true,
			'before_stats' => $before_stats,
			'after_stats'  => $after_stats,
		),
		200
	);
}

/**
 * Create a test post.
 *
 * @return WP_REST_Response
 */
function opcache_test_create_post() {
	$post_id = wp_insert_post(
		array(
			'post_title'   => 'Test Post ' . time(),
			'post_content' => 'Test content for OPcache testing.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		)
	);

	if ( is_wp_error( $post_id ) ) {
		return new WP_REST_Response(
			array(
				'error' => $post_id->get_error_message(),
			),
			500
		);
	}

	return new WP_REST_Response(
		array(
			'id'  => $post_id,
			'url' => get_permalink( $post_id ),
		),
		200
	);
}

/**
 * Simulate a plugin update to trigger the upgrader_process_complete hook.
 *
 * @return WP_REST_Response
 */
function opcache_test_simulate_update() {
	// Get OPcache stats before.
	$before_stats = null;
	if ( function_exists( 'opcache_get_status' ) ) {
		$status       = opcache_get_status( false );
		$before_stats = $status['opcache_statistics'] ?? null;
	}

	// Trigger the hook that OPcache Reset listens to.
	// This simulates what happens after a plugin/theme/core update.
	do_action( 'upgrader_process_complete', null, array( 'type' => 'plugin' ) );

	// Small delay to allow async operations.
	usleep( 100000 );

	// Get OPcache stats after.
	$after_stats = null;
	if ( function_exists( 'opcache_get_status' ) ) {
		$status      = opcache_get_status( false );
		$after_stats = $status['opcache_statistics'] ?? null;
	}

	return new WP_REST_Response(
		array(
			'success'      => true,
			'hook_fired'   => 'upgrader_process_complete',
			'before_stats' => $before_stats,
			'after_stats'  => $after_stats,
		),
		200
	);
}

/**
 * Get file cache directory information.
 *
 * @return WP_REST_Response
 */
function opcache_test_file_cache_info() {
	$file_cache_dir = ini_get( 'opcache.file_cache' );

	$info = array(
		'configured_path' => $file_cache_dir,
		'exists'          => $file_cache_dir && file_exists( $file_cache_dir ),
		'is_writable'     => $file_cache_dir && is_writable( $file_cache_dir ),
		'files'           => array(),
	);

	if ( $info['exists'] && is_dir( $file_cache_dir ) ) {
		$files = scandir( $file_cache_dir );
		if ( $files ) {
			$info['files'] = array_values( array_diff( $files, array( '.', '..' ) ) );
		}
	}

	return new WP_REST_Response( $info, 200 );
}

/**
 * Simulate a plugin deletion to trigger the deleted_plugin hook.
 *
 * @return WP_REST_Response
 */
function opcache_test_simulate_plugin_delete() {
	// Get OPcache stats before.
	$before_stats = null;
	if ( function_exists( 'opcache_get_status' ) ) {
		$status       = opcache_get_status( false );
		$before_stats = $status['opcache_statistics'] ?? null;
	}

	// Trigger the hook that OPcache Reset listens to.
	// This simulates what happens after a plugin is deleted/uninstalled.
	do_action( 'deleted_plugin', 'test-plugin/test-plugin.php', true );

	// Small delay to allow async operations.
	usleep( 100000 );

	// Get OPcache stats after.
	$after_stats = null;
	if ( function_exists( 'opcache_get_status' ) ) {
		$status      = opcache_get_status( false );
		$after_stats = $status['opcache_statistics'] ?? null;
	}

	return new WP_REST_Response(
		array(
			'success'      => true,
			'hook_fired'   => 'deleted_plugin',
			'before_stats' => $before_stats,
			'after_stats'  => $after_stats,
		),
		200
	);
}

/**
 * Install the dummy test plugin by copying it to the plugins directory.
 * Note: Activation should be done via wp-cli for reliability.
 *
 * @return WP_REST_Response
 */
function opcache_test_dummy_plugin_install() {
	$source_dir = '/workspace/tests/fixtures/dummy-plugin';
	$dest_dir   = WP_PLUGIN_DIR . '/dummy-plugin';
	$dest_file  = $dest_dir . '/dummy-plugin.php';

	// Invalidate any cached bytecode for destination file BEFORE copying.
	if ( function_exists( 'opcache_invalidate' ) ) {
		opcache_invalidate( $dest_file, true );
	}

	// Create destination directory.
	if ( ! file_exists( $dest_dir ) ) {
		mkdir( $dest_dir, 0755, true );
	}

	// Copy plugin file.
	$source_file = $source_dir . '/dummy-plugin.php';

	if ( ! file_exists( $source_file ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => 'Source plugin not found at ' . $source_file,
			),
			500
		);
	}

	copy( $source_file, $dest_file );
	clearstatcache( true, $dest_file );

	// Invalidate again AFTER copying to ensure fresh bytecode on next load.
	if ( function_exists( 'opcache_invalidate' ) ) {
		opcache_invalidate( $dest_file, true );
	}

	return new WP_REST_Response(
		array(
			'success'   => true,
			'installed' => file_exists( $dest_file ),
			'file_path' => $dest_file,
		),
		200
	);
}

/**
 * Get dummy plugin file info (check if installed, mtime, etc.).
 *
 * @return WP_REST_Response
 */
function opcache_test_dummy_plugin_build_id() {
	$plugin_file = WP_PLUGIN_DIR . '/dummy-plugin/dummy-plugin.php';
	$exists      = file_exists( $plugin_file );
	$cached      = false;

	// Check if file is in OPcache.
	if ( function_exists( 'opcache_is_script_cached' ) && $exists ) {
		$cached = opcache_is_script_cached( $plugin_file );
	}

	// Read the file content to see what build ID is on disk.
	$disk_build_id = null;
	if ( $exists ) {
		$content = file_get_contents( $plugin_file );
		if ( preg_match( "/define\\s*\\(\\s*'DUMMY_PLUGIN_BUILD_ID'\\s*,\\s*'([^']+)'/", $content, $matches ) ) {
			$disk_build_id = $matches[1];
		}
	}

	return new WP_REST_Response(
		array(
			'exists'        => $exists,
			'cached'        => $cached,
			'disk_build_id' => $disk_build_id,
			'file_path'     => $plugin_file,
			'file_mtime'    => $exists ? filemtime( $plugin_file ) : null,
		),
		200
	);
}

/**
 * Update the dummy plugin by changing its build ID on disk.
 * This simulates a plugin update where files are replaced.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response
 */
function opcache_test_dummy_plugin_update( $request ) {
	$plugin_file  = WP_PLUGIN_DIR . '/dummy-plugin/dummy-plugin.php';
	$new_build    = $request->get_param( 'build_id' );
	$trigger_hook = $request->get_param( 'trigger_hook' ) !== false; // Default true.

	if ( ! $new_build ) {
		$new_build = 'BUILD_UPDATED_' . time();
	}

	if ( ! file_exists( $plugin_file ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => 'Plugin not installed',
			),
			404
		);
	}

	// Read current content.
	$content = file_get_contents( $plugin_file );

	// Replace build ID in the define statement.
	$content = preg_replace(
		"/define\\s*\\(\\s*'DUMMY_PLUGIN_BUILD_ID'\\s*,\\s*'[^']+'/",
		"define( 'DUMMY_PLUGIN_BUILD_ID', '{$new_build}'",
		$content
	);

	// Write updated content.
	file_put_contents( $plugin_file, $content );

	// Clear file stat cache so PHP sees the new mtime.
	clearstatcache( true, $plugin_file );

	// Optionally trigger the upgrader_process_complete hook.
	if ( $trigger_hook ) {
		do_action( 'upgrader_process_complete', null, array( 'type' => 'plugin' ) );
	}

	return new WP_REST_Response(
		array(
			'success'      => true,
			'new_build_id' => $new_build,
			'file_mtime'   => filemtime( $plugin_file ),
			'hook_fired'   => $trigger_hook,
		),
		200
	);
}

/**
 * Delete the dummy plugin using WordPress delete_plugins() function.
 * This properly triggers the deleted_plugin hook in PHP-FPM context.
 * Tests memory-based OPcache clearing.
 *
 * @return WP_REST_Response
 */
function opcache_test_dummy_plugin_uninstall() {
	$plugin_file = 'dummy-plugin/dummy-plugin.php';
	$plugin_dir  = WP_PLUGIN_DIR . '/dummy-plugin';

	// Load required files for plugin deletion.
	if ( ! function_exists( 'delete_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	// Deactivate first if active.
	if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file ) ) {
		deactivate_plugins( $plugin_file );
	}

	// Use WordPress delete_plugins() which fires the deleted_plugin hook.
	$result = delete_plugins( array( $plugin_file ) );

	if ( is_wp_error( $result ) ) {
		// Fallback to manual deletion if delete_plugins fails.
		if ( file_exists( $plugin_dir ) ) {
			$files = glob( $plugin_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
			rmdir( $plugin_dir );
			// Manually fire hook since delete_plugins failed.
			do_action( 'deleted_plugin', $plugin_file, true );
		}
	}

	return new WP_REST_Response(
		array(
			'success' => true,
			'deleted' => ! file_exists( $plugin_dir ),
			'method'  => 'php_delete_plugins',
		),
		200
	);
}

/**
 * Delete the dummy plugin files directly (for cleanup).
 * Does NOT trigger any hooks - used for test cleanup only.
 *
 * @return WP_REST_Response
 */
function opcache_test_dummy_plugin_cleanup() {
	$plugin_dir  = WP_PLUGIN_DIR . '/dummy-plugin';
	$plugin_file = $plugin_dir . '/dummy-plugin.php';

	// Invalidate OPcache for the plugin file.
	if ( function_exists( 'opcache_invalidate' ) ) {
		opcache_invalidate( $plugin_file, true );
	}

	if ( file_exists( $plugin_dir ) ) {
		$files = glob( $plugin_dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		rmdir( $plugin_dir );
	}

	return new WP_REST_Response(
		array(
			'success' => true,
			'deleted' => ! file_exists( $plugin_dir ),
		),
		200
	);
}

/**
 * Activate the dummy plugin via wp-cli.
 *
 * @return WP_REST_Response
 */
function opcache_test_dummy_plugin_activate_wpcli() {
	$plugin_file = 'dummy-plugin/dummy-plugin.php';
	$plugin_path = WP_PLUGIN_DIR . '/dummy-plugin/dummy-plugin.php';

	if ( ! file_exists( $plugin_path ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => 'Plugin not installed',
			),
			404
		);
	}

	// Use wp-cli to activate.
	$output    = array();
	$exit_code = 0;
	$cmd       = 'wp plugin activate dummy-plugin --path=/var/www/html --allow-root 2>&1';

	exec( $cmd, $output, $exit_code );

	$is_active = is_plugin_active( $plugin_file );

	return new WP_REST_Response(
		array(
			'success'   => $exit_code === 0 || $is_active,
			'method'    => 'wpcli',
			'is_active' => $is_active,
			'output'    => implode( "\n", $output ),
			'exit_code' => $exit_code,
		),
		200
	);
}

/**
 * Delete the dummy plugin via wp-cli.
 * This runs in CLI context and tests file-based OPcache + cachetool clearing.
 *
 * @return WP_REST_Response
 */
function opcache_test_dummy_plugin_delete_wpcli() {
	$plugin_file = 'dummy-plugin/dummy-plugin.php';
	$plugin_dir  = WP_PLUGIN_DIR . '/dummy-plugin';

	// Check if wp-cli is available.
	$wp_cli_path = trim( shell_exec( 'which wp 2>/dev/null' ) );
	if ( empty( $wp_cli_path ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => 'wp-cli not found',
			),
			500
		);
	}

	// Run wp-cli to delete the plugin (runs in CLI context).
	// This triggers the deleted_plugin hook in CLI context,
	// which tests the file-based OPcache clearing + cachetool.
	$output = shell_exec( "wp plugin delete dummy-plugin --path=/var/www/html --allow-root 2>&1" );

	$deleted = ! file_exists( $plugin_dir );

	return new WP_REST_Response(
		array(
			'success'    => $deleted,
			'deleted'    => $deleted,
			'method'     => 'wpcli',
			'wp_cli_out' => $output,
		),
		200
	);
}

/**
 * Check if a specific file is cached in OPcache.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response
 */
function opcache_test_file_cache_status( $request ) {
	$file = $request->get_param( 'file' );

	if ( ! $file ) {
		return new WP_REST_Response(
			array( 'error' => 'file parameter required' ),
			400
		);
	}

	$full_path = WP_PLUGIN_DIR . '/' . $file;
	$exists    = file_exists( $full_path );
	$cached    = false;

	if ( function_exists( 'opcache_is_script_cached' ) ) {
		$cached = opcache_is_script_cached( $full_path );
	}

	return new WP_REST_Response(
		array(
			'file'      => $file,
			'full_path' => $full_path,
			'exists'    => $exists,
			'cached'    => $cached,
		),
		200
	);
}

/**
 * Pure PHP OPcache test - bypasses WordPress plugin system entirely.
 *
 * Tests OPcache behavior using a simple PHP file across multiple requests.
 * This proves validate_timestamps=0 works and that opcache_reset() clears the cache.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response
 */
function opcache_test_pure_php( $request ) {
	$action = $request->get_param( 'action' );
	// Create file in web root so it can be accessed via HTTP.
	$test_file = ABSPATH . 'opcache-test-file.php';
	$test_url  = home_url( '/opcache-test-file.php' );

	if ( 'setup_v1' === $action ) {
		// Step 1: Create file with V1, then invalidate any old cache.
		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $test_file, true );
		}
		// File outputs JSON with the value.
		$php_code = '<?php header("Content-Type: application/json"); echo json_encode(["value" => "V1"]);';
		file_put_contents( $test_file, $php_code );
		clearstatcache( true, $test_file );

		return new WP_REST_Response(
			array(
				'success'  => true,
				'action'   => 'setup_v1',
				'file'     => $test_file,
				'test_url' => $test_url,
			),
			200
		);
	}

	if ( 'modify_to_v2' === $action ) {
		// Step 2: Modify file to V2 (without any reset).
		$php_code = '<?php header("Content-Type: application/json"); echo json_encode(["value" => "V2"]);';
		file_put_contents( $test_file, $php_code );
		clearstatcache( true, $test_file );

		return new WP_REST_Response(
			array(
				'success'   => true,
				'action'    => 'modify_to_v2',
				'file'      => $test_file,
				'disk_hash' => md5_file( $test_file ),
			),
			200
		);
	}

	if ( 'reset' === $action ) {
		// Call opcache_reset() and clear file cache.
		$reset_result = false;
		if ( function_exists( 'opcache_reset' ) ) {
			$reset_result = opcache_reset();
		}

		// Also clear file cache.
		$file_cache_dir = ini_get( 'opcache.file_cache' );
		$file_cleared   = false;
		if ( ! empty( $file_cache_dir ) && is_dir( $file_cache_dir ) ) {
			shell_exec( "rm -rf {$file_cache_dir}/*" );
			$file_cleared = true;
		}

		return new WP_REST_Response(
			array(
				'success'            => true,
				'action'             => 'reset',
				'opcache_reset'      => $reset_result,
				'file_cache_cleared' => $file_cleared,
			),
			200
		);
	}

	if ( 'cleanup' === $action ) {
		// Cleanup.
		if ( file_exists( $test_file ) ) {
			@unlink( $test_file );
		}
		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $test_file, true );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'action'  => 'cleanup',
			),
			200
		);
	}

	if ( 'status' === $action ) {
		// Get cache status for the test file.
		$is_cached = function_exists( 'opcache_is_script_cached' ) ? opcache_is_script_cached( $test_file ) : null;
		$exists    = file_exists( $test_file );

		return new WP_REST_Response(
			array(
				'success'   => true,
				'action'    => 'status',
				'file'      => $test_file,
				'exists'    => $exists,
				'is_cached' => $is_cached,
				'test_url'  => $test_url,
			),
			200
		);
	}

	return new WP_REST_Response(
		array(
			'success' => false,
			'error'   => 'Unknown action. Use: setup_v1, modify_to_v2, reset, cleanup, status.',
		),
		400
	);
}

