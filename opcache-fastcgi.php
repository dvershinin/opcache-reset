<?php
/**
 * Direct FastCGI client for OPcache reset.
 *
 * Implements FastCGI protocol to communicate with PHP-FPM directly,
 * eliminating the need for cachetool binary.
 *
 * Note: This file uses low-level PHP socket functions intentionally.
 * WP_Filesystem is not suitable for socket communication.
 *
 * @package opcache-reset
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// FastCGI protocol constants.
define( 'FCGI_VERSION_1', 1 );
define( 'FCGI_BEGIN_REQUEST', 1 );
define( 'FCGI_END_REQUEST', 3 );
define( 'FCGI_PARAMS', 4 );
define( 'FCGI_STDIN', 5 );
define( 'FCGI_STDOUT', 6 );
define( 'FCGI_RESPONDER', 1 );

/**
 * Build a FastCGI record.
 *
 * @param int    $type       Record type.
 * @param string $content    Record content.
 * @param int    $request_id Request ID.
 * @return string Binary record data.
 */
function gps_fcgi_build_record( $type, $content, $request_id = 1 ) {
	$content_length = strlen( $content );
	$padding_length = ( 8 - ( $content_length % 8 ) ) % 8;

	return pack(
		'CCnnCx',
		FCGI_VERSION_1,
		$type,
		$request_id,
		$content_length,
		$padding_length
	) . $content . str_repeat( "\0", $padding_length );
}

/**
 * Build FastCGI name-value pair.
 *
 * @param string $name  Parameter name.
 * @param string $value Parameter value.
 * @return string Binary name-value pair.
 */
function gps_fcgi_build_nvpair( $name, $value ) {
	$name_len  = strlen( $name );
	$value_len = strlen( $value );

	$result = '';
	if ( $name_len < 128 ) {
		$result .= chr( $name_len );
	} else {
		$result .= pack( 'N', $name_len | 0x80000000 );
	}
	if ( $value_len < 128 ) {
		$result .= chr( $value_len );
	} else {
		$result .= pack( 'N', $value_len | 0x80000000 );
	}

	return $result . $name . $value;
}

/**
 * Find cachetool configuration file.
 *
 * Searches in WordPress root, then home directory.
 *
 * @return string|null Path to config file or null if not found.
 */
function gps_find_cachetool_config() {
	$locations = array(
		ABSPATH . '.cachetool.yml',
		ABSPATH . 'cachetool.yml',
	);

	$home = getenv( 'HOME' );
	if ( $home ) {
		$locations[] = $home . '/.cachetool.yml';
	}

	foreach ( $locations as $path ) {
		if ( file_exists( $path ) && is_readable( $path ) ) {
			return $path;
		}
	}

	return null;
}

/**
 * Parse cachetool.yml configuration file.
 *
 * Simple YAML parser for the adapter and fastcgi keys.
 *
 * @param string $path Path to the config file.
 * @return string|null Socket path/address or null if invalid.
 */
function gps_parse_cachetool_yml( $path ) {
	$contents = file_get_contents( $path );
	if ( false === $contents ) {
		return null;
	}

	$adapter = null;
	$fastcgi = null;

	$lines = explode( "\n", $contents );
	foreach ( $lines as $line ) {
		$line = trim( $line );

		// Skip comments and empty lines.
		if ( empty( $line ) || '#' === $line[0] ) {
			continue;
		}

		// Parse key: value format.
		if ( preg_match( '/^(\w+)\s*:\s*(.+)$/', $line, $matches ) ) {
			$key   = strtolower( $matches[1] );
			$value = trim( $matches[2], " \t\n\r\0\x0B\"'" );

			if ( 'adapter' === $key ) {
				$adapter = $value;
			} elseif ( 'fastcgi' === $key ) {
				$fastcgi = $value;
			}
		}
	}

	// Only return socket if adapter is fastcgi.
	if ( 'fastcgi' === $adapter && $fastcgi ) {
		return $fastcgi;
	}

	return null;
}

/**
 * Reset OPcache via direct FastCGI communication.
 *
 * Sends a FastCGI request directly to PHP-FPM with a custom parameter
 * that triggers the reset handler in opcache-reset.php.
 * No temporary files needed - completely inline.
 *
 * Security: The GPS_OPCACHE_RESET_INTERNAL param can only be set via
 * direct FastCGI connection. nginx/Apache never forward arbitrary
 * custom CGI params - only HTTP_* headers from HTTP requests.
 *
 * @param string $socket Socket path (Unix) or address (TCP host:port).
 * @return bool True on success, false on failure.
 */
function gps_fastcgi_opcache_reset( $socket ) {
	// Determine connection string.
	if ( strpos( $socket, '/' ) === 0 ) {
		// Unix socket.
		$connect_string = 'unix://' . $socket;
	} elseif ( strpos( $socket, ':' ) !== false ) {
		// TCP socket (host:port).
		$connect_string = 'tcp://' . $socket;
	} else {
		// Assume TCP with default port.
		$connect_string = 'tcp://' . $socket . ':9000';
	}

	// Connect to PHP-FPM. Silence errors - we check $fp instead.
	$fp = @stream_socket_client( $connect_string, $errno, $errstr, 5 );
	if ( ! $fp ) {
		return false;
	}

	// Use the plugin's own file as the script.
	$script_filename = __DIR__ . '/opcache-reset.php';

	// Build FCGI_BEGIN_REQUEST.
	$begin_request = pack( 'nCCCCCC', FCGI_RESPONDER, 0, 0, 0, 0, 0, 0, 0 );
	$request       = gps_fcgi_build_record( FCGI_BEGIN_REQUEST, $begin_request );

	// Build params.
	// GPS_OPCACHE_RESET_INTERNAL is a custom param that triggers the reset handler.
	// This param can ONLY be set via direct FastCGI - nginx/Apache never forward it.
	$params  = '';
	$params .= gps_fcgi_build_nvpair( 'SCRIPT_FILENAME', $script_filename );
	$params .= gps_fcgi_build_nvpair( 'SCRIPT_NAME', '/opcache-reset.php' );
	$params .= gps_fcgi_build_nvpair( 'REQUEST_METHOD', 'GET' );
	$params .= gps_fcgi_build_nvpair( 'QUERY_STRING', '' );
	$params .= gps_fcgi_build_nvpair( 'REQUEST_URI', '/opcache-reset.php' );
	$params .= gps_fcgi_build_nvpair( 'DOCUMENT_ROOT', __DIR__ );
	$params .= gps_fcgi_build_nvpair( 'SERVER_PROTOCOL', 'HTTP/1.1' );
	$params .= gps_fcgi_build_nvpair( 'GATEWAY_INTERFACE', 'CGI/1.1' );
	$params .= gps_fcgi_build_nvpair( 'SERVER_SOFTWARE', 'opcache-reset' );
	$params .= gps_fcgi_build_nvpair( 'REMOTE_ADDR', '127.0.0.1' );
	$params .= gps_fcgi_build_nvpair( 'REMOTE_PORT', '0' );
	$params .= gps_fcgi_build_nvpair( 'SERVER_ADDR', '127.0.0.1' );
	$params .= gps_fcgi_build_nvpair( 'SERVER_PORT', '80' );
	$params .= gps_fcgi_build_nvpair( 'SERVER_NAME', 'localhost' );
	// Custom param to trigger reset - not forwardable via HTTP.
	$params .= gps_fcgi_build_nvpair( 'GPS_OPCACHE_RESET_INTERNAL', '1' );

	$request .= gps_fcgi_build_record( FCGI_PARAMS, $params );
	$request .= gps_fcgi_build_record( FCGI_PARAMS, '' ); // Empty params to end.
	$request .= gps_fcgi_build_record( FCGI_STDIN, '' );  // Empty stdin.

	// Send request.
	fwrite( $fp, $request );

	// Read response.
	$response        = '';
	$response_length = 0;
	while ( ! feof( $fp ) ) {
		$data = fread( $fp, 8192 );
		if ( false === $data ) {
			break;
		}
		$response       .= $data;
		$response_length = strlen( $response );

		// Check if we received FCGI_END_REQUEST.
		if ( $response_length >= 8 ) {
			$pos = 0;
			while ( $pos + 8 <= $response_length ) {
				$header = unpack( 'Cversion/Ctype/nrequestId/ncontentLength/CpaddingLength', substr( $response, $pos, 8 ) );
				if ( FCGI_END_REQUEST === $header['type'] ) {
					fclose( $fp );
					return true;
				}
				$pos += 8 + $header['contentLength'] + $header['paddingLength'];
			}
		}
	}

	fclose( $fp );
	return true;
}
