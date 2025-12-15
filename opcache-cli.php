<?php
/**
 * WP-CLI commands for OPcache management.
 *
 * @package opcache-reset
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load if WP-CLI is available.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage OPcache from the command line.
 *
 * ## EXAMPLES
 *
 *     # Reset OPcache
 *     $ wp opcache reset
 *     Success: OPcache has been reset.
 *
 *     # Show OPcache status
 *     $ wp opcache status
 *     OPcache is enabled.
 *     Cached scripts: 1234
 *     Memory usage: 64.5 MB / 128 MB
 *
 * @package opcache-reset
 */
class OPcache_CLI_Command {

	/**
	 * Reset OPcache.
	 *
	 * Clears both memory-based and file-based OPcache.
	 * Uses the same reset mechanism as automatic plugin/theme updates.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Force reset even if OPcache appears disabled.
	 *
	 * ## EXAMPLES
	 *
	 *     # Reset OPcache
	 *     $ wp opcache reset
	 *     Success: OPcache has been reset.
	 *
	 *     # Force reset
	 *     $ wp opcache reset --force
	 *     Success: OPcache has been reset.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function reset( $args, $assoc_args ) {
		$force = WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );

		if ( ! function_exists( 'opcache_reset' ) && ! $force ) {
			WP_CLI::error( 'OPcache extension is not loaded. Use --force to attempt reset anyway.' );
			return;
		}

		if ( empty( ini_get( 'opcache.enable' ) ) && ! $force ) {
			WP_CLI::error( 'OPcache is disabled. Use --force to attempt reset anyway.' );
			return;
		}

		// Call the plugin's reset function.
		if ( function_exists( 'gps_opcache_reset' ) ) {
			gps_opcache_reset();
			WP_CLI::success( 'OPcache has been reset.' );
		} else {
			WP_CLI::error( 'OPcache Reset plugin function not found. Is the plugin active?' );
		}
	}

	/**
	 * Show OPcache status and statistics.
	 *
	 * Displays current OPcache configuration and usage statistics.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show status as table
	 *     $ wp opcache status
	 *
	 *     # Show status as JSON
	 *     $ wp opcache status --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ) {
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( ! function_exists( 'opcache_get_status' ) ) {
			WP_CLI::error( 'OPcache extension is not loaded.' );
			return;
		}

		$status = opcache_get_status( false );
		$config = opcache_get_configuration();

		if ( false === $status ) {
			WP_CLI::error( 'Failed to get OPcache status. OPcache may be disabled.' );
			return;
		}

		$stats      = $status['opcache_statistics'] ?? array();
		$memory     = $status['memory_usage'] ?? array();
		$directives = $config['directives'] ?? array();

		// Calculate memory usage.
		$used_memory  = $memory['used_memory'] ?? 0;
		$free_memory  = $memory['free_memory'] ?? 0;
		$total_memory = $used_memory + $free_memory;

		$data = array(
			array(
				'Property' => 'Status',
				'Value'    => ( $status['opcache_enabled'] ?? false ) ? 'Enabled' : 'Disabled',
			),
			array(
				'Property' => 'Cached Scripts',
				'Value'    => $stats['num_cached_scripts'] ?? 0,
			),
			array(
				'Property' => 'Cache Hits',
				'Value'    => $stats['hits'] ?? 0,
			),
			array(
				'Property' => 'Cache Misses',
				'Value'    => $stats['misses'] ?? 0,
			),
			array(
				'Property' => 'Memory Used',
				'Value'    => $this->format_bytes( $used_memory ),
			),
			array(
				'Property' => 'Memory Free',
				'Value'    => $this->format_bytes( $free_memory ),
			),
			array(
				'Property' => 'Memory Total',
				'Value'    => $this->format_bytes( $total_memory ),
			),
			array(
				'Property' => 'Hit Rate',
				'Value'    => round( $stats['opcache_hit_rate'] ?? 0, 2 ) . '%',
			),
			array(
				'Property' => 'File Cache',
				'Value'    => $directives['opcache.file_cache'] ?? 'Disabled',
			),
			array(
				'Property' => 'Validate Timestamps',
				'Value'    => ( $directives['opcache.validate_timestamps'] ?? true ) ? 'Yes' : 'No',
			),
		);

		if ( 'json' === $format ) {
			// For JSON, output raw data.
			$json_data = array(
				'enabled'             => $status['opcache_enabled'] ?? false,
				'cached_scripts'      => $stats['num_cached_scripts'] ?? 0,
				'hits'                => $stats['hits'] ?? 0,
				'misses'              => $stats['misses'] ?? 0,
				'memory_used'         => $used_memory,
				'memory_free'         => $free_memory,
				'memory_total'        => $total_memory,
				'hit_rate'            => $stats['opcache_hit_rate'] ?? 0,
				'file_cache'          => $directives['opcache.file_cache'] ?? null,
				'validate_timestamps' => $directives['opcache.validate_timestamps'] ?? true,
			);
			WP_CLI::line( wp_json_encode( $json_data, JSON_PRETTY_PRINT ) );
		} else {
			WP_CLI\Utils\format_items( $format, $data, array( 'Property', 'Value' ) );
		}
	}

	/**
	 * Format bytes to human-readable string.
	 *
	 * @param int $bytes Number of bytes.
	 * @return string Formatted string.
	 */
	private function format_bytes( $bytes ) {
		if ( $bytes >= 1073741824 ) {
			return round( $bytes / 1073741824, 2 ) . ' GB';
		} elseif ( $bytes >= 1048576 ) {
			return round( $bytes / 1048576, 2 ) . ' MB';
		} elseif ( $bytes >= 1024 ) {
			return round( $bytes / 1024, 2 ) . ' KB';
		}
		return $bytes . ' bytes';
	}
}

WP_CLI::add_command( 'opcache', 'OPcache_CLI_Command' );
