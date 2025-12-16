<?php
/**
 * MaxtDesign PDF Viewer
 *
 * @package     MaxtDesign\PDFViewer
 * @author      MaxtDesign
 * @copyright   2025 MaxtDesign
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: MaxtDesign PDF Viewer
 * Plugin URI: https://wordpress.org/plugins/maxtdesign-pdf-viewer/
 * Description: The fastest PDF viewer for WordPress. Sub-200ms load, zero layout shift, server-side preview extraction.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Tested up to: 6.9
 * Requires PHP: 8.1
 * Author: MaxtDesign
 * Author URI: https://maxtdesign.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: maxtdesign-pdf-viewer
 * Domain Path: /languages
 */

declare(strict_types=1);

// Security check - exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Check PHP version before anything else
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	/**
	 * Display admin notice for PHP version requirement
	 *
	 * @return void
	 */
	function mdpv_php_version_notice(): void {
		$message = sprintf(
			/* translators: 1: Plugin name, 2: Required PHP version, 3: Current PHP version */
			esc_html__( '%1$s requires PHP version %2$s or higher. You are running PHP %3$s. Please upgrade your PHP version.', 'maxtdesign-pdf-viewer' ),
			'<strong>MaxtDesign PDF Viewer</strong>',
			'8.1',
			esc_html( PHP_VERSION )
		);
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Message is already escaped via esc_html__ and esc_html
		printf( '<div class="notice notice-error"><p>%s</p></div>', $message );
	}

	add_action( 'admin_notices', 'mdpv_php_version_notice' );
	return;
}

// Define plugin constants
define( 'MDPV_VERSION', '1.0.0' );
define( 'MDPV_PLUGIN_FILE', __FILE__ );
define( 'MDPV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MDPV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MDPV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Require the main Plugin class file
require_once MDPV_PLUGIN_DIR . 'includes/class-mdpv-plugin.php';

/**
 * Initialize the plugin
 *
 * @return MaxtDesign\PDFViewer\Plugin|null
 */
function mdpv_init(): ?MaxtDesign\PDFViewer\Plugin {
	try {
		return MaxtDesign\PDFViewer\Plugin::instance();
	} catch ( \Exception $e ) {
		// Log error but don't break plugin discovery
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only used in debug mode
			error_log( 'MaxtDesign PDF Viewer initialization error: ' . $e->getMessage() );
		}
		return null;
	}
}

// Initialize the plugin
mdpv_init();

/**
 * Add settings link to plugin actions
 *
 * @param array $links Plugin action links.
 * @return array Modified links.
 */
function mdpv_plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'options-general.php?page=mdpv-settings' ),
		__( 'Settings', 'maxtdesign-pdf-viewer' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mdpv_plugin_action_links' );

