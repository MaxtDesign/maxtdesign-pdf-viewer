<?php
/**
 * Uninstall handler for MaxtDesign PDF Viewer
 *
 * Removes all plugin data when plugin is deleted.
 *
 * @package     MaxtDesign\PDFViewer
 * @since       1.0.0
 * @author      MaxtDesign
 * @copyright   2025 MaxtDesign
 * @license     GPL-2.0-or-later
 */

// Exit if not called by WordPress uninstall
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'mdpv_settings' );
delete_option( 'mdpv_last_cleanup' );

// Delete transients
delete_transient( 'mdpv_activated' );
delete_transient( 'mdpv_server_capabilities' );

// Delete all post meta with our prefix
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_mdpv\_%'"
);

// Delete cache directory and contents
$mdpv_upload_dir = wp_upload_dir();
$mdpv_cache_dir  = trailingslashit( $mdpv_upload_dir['basedir'] ) . 'mdpv-cache';

if ( is_dir( $mdpv_cache_dir ) ) {
	// Delete all files in cache directory
	// Get all files including hidden ones (like .htaccess)
	$mdpv_regular_files = glob( $mdpv_cache_dir . '*' );
	$mdpv_hidden_files  = glob( $mdpv_cache_dir . '.*' );

	$mdpv_files = [];
	if ( false !== $mdpv_regular_files ) {
		$mdpv_files = array_merge( $mdpv_files, $mdpv_regular_files );
	}
	if ( false !== $mdpv_hidden_files ) {
		$mdpv_files = array_merge( $mdpv_files, $mdpv_hidden_files );
	}

	if ( ! empty( $mdpv_files ) ) {
		foreach ( $mdpv_files as $mdpv_file ) {
			// Skip . and .. entries
			if ( in_array( basename( $mdpv_file ), [ '.', '..' ], true ) ) {
				continue;
			}

			if ( is_file( $mdpv_file ) ) {
				wp_delete_file( $mdpv_file );
			}
		}
	}

	// Remove the directory itself
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- WP_Filesystem not available in uninstall context
	rmdir( $mdpv_cache_dir );
}

// Clear any scheduled events
wp_clear_scheduled_hook( 'mdpv_cleanup_cache' );

