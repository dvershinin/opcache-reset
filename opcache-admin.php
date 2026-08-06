<?php
/**
 * OPcache Reset Admin functionality.
 *
 * Handles admin notices and file invalidation in the plugin/theme editors.
 *
 * @package opcache-reset
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the Linux distribution name.
 *
 * @return string|null The distribution name or null if not found.
 */
function gps_opcache_get_linux_distro() {
	// Declare Linux distros (extensible list).
	$distros = array(
		'Arch'   => 'arch-release',
		'Debian' => 'debian_version',
		'Fedora' => 'fedora-release',
		'Ubuntu' => 'lsb-release',
		'Redhat' => 'redhat-release',
		'CentOS' => 'centos-release',
	);

	// Get everything from /etc directory.
	$etc_list = scandir( '/etc' );

	// Loop through /etc results.
	$os_distro = null;
	foreach ( $etc_list as $entry ) {
		// Loop through list of distros.
		foreach ( $distros as $distro_release_file ) {
			// Match was found.
			if ( $distro_release_file === $entry ) {
				// Find distros array key (i.e. Distro name) by value (i.e. distro release file).
				$os_distro = array_search( $distro_release_file, $distros, true );
				break 2; // Break inner and outer loop.
			}
		}
	}

	return $os_distro;
}

/**
 * Display success notice for OPcache invalidation.
 */
function gps_opcache_edit_success() {
	?>
	<style>.updated.notice { display: none; }</style>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'File edited successfully and invalidated in bytecode cache.', 'opcache-reset' ); ?></p>
	</div>
	<?php
}

/**
 * Display warning notice when OPcache had nothing to invalidate.
 */
function gps_opcache_edit_warning() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in calling function.
	$file = isset( $_REQUEST['file'] ) ? sanitize_file_name( wp_unslash( $_REQUEST['file'] ) ) : '';
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
		<?php
		printf(
			/* translators: %s: file path */
			esc_html__( 'OPcache had nothing to invalidate for %s.', 'opcache-reset' ),
			esc_html( WP_PLUGIN_DIR . '/' . $file )
		);
		?>
		</p>
	</div>
	<?php
}

/**
 * Hook into plugin editor to invalidate OPcache for edited files.
 */
function gps_opcache_plugin_editor() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This runs after WordPress core has already verified the nonce.
	if ( isset( $_GET['a'] ) && isset( $_REQUEST['file'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$file = sanitize_file_name( wp_unslash( $_REQUEST['file'] ) );
		// File was edited successfully and we know the filename.
		if ( opcache_invalidate( WP_PLUGIN_DIR . '/' . $file ) ) {
			add_action( 'admin_notices', 'gps_opcache_edit_success', 999 );
		} else {
			add_action( 'admin_notices', 'gps_opcache_edit_warning', 999 );
		}
	}
}
add_action( 'load-plugin-editor.php', 'gps_opcache_plugin_editor' );

/**
 * Handle OPcache invalidation on admin init for file edits.
 */
function gps_opcache_admin_init() {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && ( current_user_can( 'edit_themes' ) || current_user_can( 'edit_plugins' ) ) ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- This runs after WordPress core has already verified the nonce for file editing.
		if ( isset( $_REQUEST['file'] ) ) {
			$file = sanitize_text_field( wp_unslash( $_REQUEST['file'] ) );
			opcache_invalidate( $file );
		} elseif ( isset( $_REQUEST['action'] ) && isset( $_REQUEST['filename'] ) ) {
			$filename = sanitize_text_field( wp_unslash( $_REQUEST['filename'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
			opcache_invalidate( $filename );
		}
	}
}
add_action( 'init', 'gps_opcache_admin_init', 1 );
