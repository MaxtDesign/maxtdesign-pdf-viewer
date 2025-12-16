<?php
/**
 * Main Plugin Class
 *
 * This class serves as the central orchestrator for the plugin.
 * It handles initialization, dependency loading, service registration,
 * and WordPress hook integration.
 *
 * @package     MaxtDesign\PDFViewer
 * @since       1.0.0
 * @author      MaxtDesign
 * @copyright   2025 MaxtDesign
 * @license     GPL-2.0-or-later
 */

declare(strict_types=1);

namespace MaxtDesign\PDFViewer;

// Security check - exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Main Plugin Class
 *
 * Singleton pattern implementation for plugin bootstrap.
 */
class Plugin {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public const VERSION = '1.0.0';

	/**
	 * Database version for future migrations
	 *
	 * @var int
	 */
	public const DB_VERSION = 1;

	/**
	 * Minimum required PHP version
	 *
	 * @var string
	 */
	public const MIN_PHP = '8.1';

	/**
	 * Minimum required WordPress version
	 *
	 * @var string
	 */
	public const MIN_WP = '6.4';

	/**
	 * Plugin text domain
	 *
	 * @var string
	 */
	public const TEXT_DOMAIN = 'maxtdesign-pdf-viewer';

	/**
	 * Plugin instance
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Settings service instance
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Cache service instance
	 *
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Extractor service instance
	 *
	 * @var Extractor
	 */
	private Extractor $extractor;

	/**
	 * Compatibility service instance
	 *
	 * @var Compatibility
	 */
	private Compatibility $compatibility;

	/**
	 * Admin service instance
	 *
	 * @var Admin|null
	 */
	private ?Admin $admin = null;

	/**
	 * REST API instance
	 *
	 * @var REST_API|null
	 */
	private ?REST_API $rest_api = null;

	/**
	 * Flag for shortcode usage detection
	 *
	 * @var bool
	 */
	private bool $has_shortcode = false;

	/**
	 * Get plugin instance (singleton)
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * Private to prevent direct instantiation.
	 */
	private function __construct() {
		$this->define_constants();
		$this->check_requirements();
		$this->load_dependencies();
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Prevent cloning
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization
	 *
	 * @return void
	 * @throws \Exception Always throws exception.
	 */
	public function __wakeup(): void {
		throw new \Exception(
			esc_html__( 'Cannot unserialize singleton', 'maxtdesign-pdf-viewer' )
		);
	}

	/**
	 * Define plugin constants
	 *
	 * Only defines constants if they are not already defined
	 * (they should already be set in the main plugin file).
	 *
	 * @return void
	 */
	private function define_constants(): void {
		// These constants should already be defined in the main plugin file.
		// This method serves as a backup safety check.
		if ( ! defined( 'MDPV_VERSION' ) ) {
			define( 'MDPV_VERSION', self::VERSION );
		}

		// Note: MDPV_PLUGIN_FILE should be defined in main plugin file.
		// If not defined, we cannot safely define dependent constants.
		if ( defined( 'MDPV_PLUGIN_FILE' ) ) {
			if ( ! defined( 'MDPV_PLUGIN_DIR' ) ) {
				define( 'MDPV_PLUGIN_DIR', plugin_dir_path( MDPV_PLUGIN_FILE ) );
			}

			if ( ! defined( 'MDPV_PLUGIN_URL' ) ) {
				define( 'MDPV_PLUGIN_URL', plugin_dir_url( MDPV_PLUGIN_FILE ) );
			}

			if ( ! defined( 'MDPV_PLUGIN_BASENAME' ) ) {
				define( 'MDPV_PLUGIN_BASENAME', plugin_basename( MDPV_PLUGIN_FILE ) );
			}
		}
	}

	/**
	 * Check plugin requirements
	 *
	 * Verifies WordPress version meets minimum requirement.
	 * PHP version is already checked in the main plugin file.
	 *
	 * @return void
	 * @throws \Exception If WordPress version is insufficient.
	 */
	private function check_requirements(): void {
		global $wp_version;

		if ( version_compare( $wp_version, self::MIN_WP, '<' ) ) {
			$message = sprintf(
				/* translators: 1: Plugin name, 2: Required WordPress version, 3: Current WordPress version */
				esc_html__( '%1$s requires WordPress version %2$s or higher. You are running WordPress %3$s. Please upgrade WordPress.', 'maxtdesign-pdf-viewer' ),
				'MaxtDesign PDF Viewer',
				esc_html( self::MIN_WP ),
				esc_html( $wp_version )
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Message is already escaped via esc_html__ and esc_html
			throw new \Exception( $message );
		}
	}

	/**
	 * Load plugin dependencies
	 *
	 * Requires all necessary class files.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		$includes_dir = MDPV_PLUGIN_DIR . 'includes/';

		// Core classes
		require_once $includes_dir . 'class-mdpv-settings.php';
		require_once $includes_dir . 'class-mdpv-cache.php';
		require_once $includes_dir . 'class-mdpv-compatibility.php';
		require_once $includes_dir . 'class-mdpv-extractor.php';
		require_once $includes_dir . 'class-mdpv-renderer.php';
		require_once $includes_dir . 'class-mdpv-block.php';
		require_once $includes_dir . 'class-mdpv-rest-api.php';

		// Conditionally load admin class if it exists
		if ( is_admin() ) {
			$admin_file = MDPV_PLUGIN_DIR . 'admin/class-mdpv-admin.php';
			if ( file_exists( $admin_file ) ) {
				require_once $admin_file;
			}
		}
	}

	/**
	 * Initialize plugin services
	 *
	 * Instantiates core service classes (Settings, Cache, Compatibility, Extractor).
	 *
	 * @return void
	 */
	private function init_services(): void {
		$this->settings     = new Settings();
		$this->cache        = new Cache( $this->settings );
		$this->compatibility = new Compatibility();
		$this->extractor    = new Extractor( $this->cache, $this->settings, $this->compatibility );

		// Initialize admin class if in admin
		if ( is_admin() && class_exists( __NAMESPACE__ . '\\Admin' ) ) {
			$this->admin = new Admin( $this );
			$this->admin->init();
		}
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * Registers activation/deactivation hooks and action hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Activation and deactivation hooks
		register_activation_hook( MDPV_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( MDPV_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// WordPress action hooks
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Register Gutenberg block
		add_action( 'init', array( Block::class, 'register' ) );

		// Register shortcode (keep for backward compatibility)
		add_shortcode( 'pdf_viewer', array( Renderer::class, 'shortcode_handler' ) );

		// Track shortcode usage for conditional asset loading
		add_filter( 'the_content', array( $this, 'detect_shortcode_usage' ), 0 );

		// PDF upload processing
		add_action( 'add_attachment', array( $this->extractor, 'process_upload' ) );

		// Secondary hook
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'process_attachment_metadata' ), 10, 2 );

		// Cleanup hook
		add_action( 'delete_attachment', array( $this, 'cleanup_attachment' ) );

		// Schedule cache cleanup
		if ( ! wp_next_scheduled( 'mdpv_cleanup_cache' ) ) {
			wp_schedule_event( time(), 'daily', 'mdpv_cleanup_cache' );
		}
		add_action( 'mdpv_cleanup_cache', array( $this, 'run_cache_cleanup' ) );

		// Register REST API routes
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Initialize plugin
	 *
	 * Performs initialization tasks.
	 * Note: Text domain is auto-loaded by WordPress.org for hosted plugins.
	 *
	 * @return void
	 */
	public function init(): void {
		// Text domain is automatically loaded by WordPress.org for hosted plugins.
		// For self-hosted installations, translations should be placed in /languages/ directory.
	}

	/**
	 * Enqueue frontend assets
	 *
	 * Only loads assets on pages that contain PDF viewers.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		// Check if page needs our assets
		if ( ! $this->page_needs_assets() ) {
			return;
		}

		// Enqueue styles
		wp_enqueue_style(
			'mdpv-viewer',
			MDPV_PLUGIN_URL . 'assets/css/mdpv-viewer.css',
			array(),
			self::VERSION
		);

		// Note: PDF.js is loaded dynamically by mdpv-viewer.js when needed
		// Files should be in vendor/pdfjs/ directory:
		// - pdf.min.mjs
		// - pdf.worker.min.mjs

		// Enqueue loader script
		wp_enqueue_script(
			'mdpv-loader',
			MDPV_PLUGIN_URL . 'assets/js/mdpv-loader.js',
			array(),
			self::VERSION,
			true // Load in footer
		);

		// Add module type for ES6 imports
		add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 3 );

		// Localize script with configuration
		wp_localize_script(
			'mdpv-loader',
			'mdpvConfig',
			$this->get_frontend_config()
		);
	}

	/**
	 * Plugin activation handler
	 *
	 * Creates cache directory, sets default settings,
	 * and flushes rewrite rules.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Create cache directory
		$this->cache->create_cache_directory();

		// Set default settings
		$this->settings->set_defaults();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set activation transient for admin notice
		set_transient( 'mdpv_activated', true, 30 );
	}

	/**
	 * Plugin deactivation handler
	 *
	 * Clears scheduled hooks and flushes rewrite rules.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Clear scheduled cache cleanup
		wp_clear_scheduled_hook( 'mdpv_cleanup_cache' );

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Get Settings service instance
	 *
	 * @return Settings
	 */
	public function get_settings(): Settings {
		return $this->settings;
	}

	/**
	 * Get Cache service instance
	 *
	 * @return Cache
	 */
	public function get_cache(): Cache {
		return $this->cache;
	}

	/**
	 * Get Extractor service instance
	 *
	 * @return Extractor
	 */
	public function get_extractor(): Extractor {
		return $this->extractor;
	}

	/**
	 * Get Compatibility service instance
	 *
	 * @return Compatibility
	 */
	public function get_compatibility(): Compatibility {
		return $this->compatibility;
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$this->rest_api = new REST_API( $this );
		$this->rest_api->register_routes();
	}

	/**
	 * Process attachment metadata
	 *
	 * Hooked to 'wp_generate_attachment_metadata' filter.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Attachment metadata.
	 */
	public function process_attachment_metadata( array $metadata, int $attachment_id ): array {
		// Placeholder for future implementation
		return $metadata;
	}

	/**
	 * Cleanup attachment data
	 *
	 * Hooked to 'delete_attachment' action.
	 *
	 * @param int $attachment_id Attachment ID being deleted.
	 * @return void
	 */
	public function cleanup_attachment( int $attachment_id ): void {
		$this->cache->delete_preview( $attachment_id );
	}

	/**
	 * Run cache cleanup
	 *
	 * Hooked to 'mdpv_cleanup_cache' scheduled action.
	 *
	 * @return void
	 */
	public function run_cache_cleanup(): void {
		$this->cache->cleanup_old_files();
	}

	/**
	 * Detect shortcode usage in content
	 *
	 * @param string $content Post content.
	 * @return string Unmodified content.
	 */
	public function detect_shortcode_usage( string $content ): string {
		if ( has_shortcode( $content, 'pdf_viewer' ) ) {
			$this->has_shortcode = true;
		}
		return $content;
	}

	/**
	 * Check if current page needs PDF viewer assets
	 *
	 * @return bool True if assets should be loaded.
	 */
	private function page_needs_assets(): bool {
		// Check for Gutenberg block
		if ( Block::post_has_block() ) {
			return true;
		}

		// Check for shortcode
		if ( $this->has_shortcode ) {
			return true;
		}

		// Check for manual override
		if ( apply_filters( 'mdpv_force_load_assets', false ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current post has PDF viewer block
	 *
	 * @return bool True if post contains PDF viewer block.
	 */
	private function page_has_pdf_block(): bool {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		// Check for our Gutenberg block
		if ( has_block( 'maxtdesign/pdf-viewer', $post ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current post has PDF viewer shortcode
	 *
	 * @return bool True if post contains PDF viewer shortcode.
	 */
	private function page_has_shortcode(): bool {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		// Check post content for shortcode
		if ( has_shortcode( $post->post_content, 'pdf_viewer' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Add type="module" to loader script for ES6 dynamic imports
	 *
	 * @param string $tag    Script HTML tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source URL.
	 * @return string Modified script tag.
	 */
	public function add_module_type( string $tag, string $handle, string $src ): string {
		// Add module type for loader script
		if ( 'mdpv-loader' === $handle ) {
			// Replace type or add it
			if ( strpos( $tag, 'type=' ) !== false ) {
				$tag = preg_replace( '/type=[\'"][^\'"]*[\'"]/', 'type="module"', $tag );
			} else {
				$tag = str_replace( ' src=', ' type="module" src=', $tag );
			}
		}
		return $tag;
	}

	/**
	 * Get frontend JavaScript configuration
	 *
	 * @return array Configuration array for wp_localize_script.
	 */
	private function get_frontend_config(): array {
		// Check if PDF.js files exist locally
		$pdfjs_url = null;
		$pdfjs_path = MDPV_PLUGIN_DIR . 'vendor/pdfjs/pdf.min.mjs';
		
		// Normalize path for Windows compatibility
		$pdfjs_path = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $pdfjs_path );
		
		if ( file_exists( $pdfjs_path ) ) {
			$pdfjs_url = MDPV_PLUGIN_URL . 'vendor/pdfjs/pdf.min.mjs';
		}

		$worker_url = MDPV_PLUGIN_URL . 'vendor/pdfjs/pdf.worker.min.mjs';
		$worker_path = MDPV_PLUGIN_DIR . 'vendor/pdfjs/pdf.worker.min.mjs';
		
		// Normalize path for Windows compatibility
		$worker_path = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $worker_path );
		
		// Check if worker file exists locally
		if ( ! file_exists( $worker_path ) ) {
			// Local file should exist - log warning but don't use CDN
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'MDPV: Local PDF.js worker file not found at: ' . $worker_path );
			}
		}

		// Check if cmaps directory exists locally
		$cmaps_url = null;
		$cmaps_path = MDPV_PLUGIN_DIR . 'vendor/pdfjs/cmaps/';
		$cmaps_path = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $cmaps_path );
		
		if ( is_dir( $cmaps_path ) ) {
			$cmaps_url = MDPV_PLUGIN_URL . 'vendor/pdfjs/cmaps/';
		}

		return array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'restUrl'      => rest_url( 'mdpv/v1' ),
			'nonce'        => wp_create_nonce( 'mdpv-viewer' ),
			'viewerUrl'    => MDPV_PLUGIN_URL . 'assets/js/mdpv-viewer.js',
			'pdfjsUrl'     => $pdfjs_url, // Local PDF.js URL if available
			'pdfWorkerUrl' => $worker_url,
			'cmapsUrl'     => $cmaps_url, // Local cmaps URL if available
			'pluginUrl'    => MDPV_PLUGIN_URL,
			'i18n'         => array(
				'loading'        => __( 'Loading document...', 'maxtdesign-pdf-viewer' ),
				'error'          => __( 'Failed to load document', 'maxtdesign-pdf-viewer' ),
				'loadError'      => __( 'Failed to load viewer module', 'maxtdesign-pdf-viewer' ),
				'page'           => __( 'Page', 'maxtdesign-pdf-viewer' ),
				'of'             => __( 'of', 'maxtdesign-pdf-viewer' ),
				'zoom'           => __( 'Zoom', 'maxtdesign-pdf-viewer' ),
				'zoomIn'         => __( 'Zoom in', 'maxtdesign-pdf-viewer' ),
				'zoomOut'        => __( 'Zoom out', 'maxtdesign-pdf-viewer' ),
				'prevPage'       => __( 'Previous page', 'maxtdesign-pdf-viewer' ),
				'nextPage'       => __( 'Next page', 'maxtdesign-pdf-viewer' ),
				'download'       => __( 'Download', 'maxtdesign-pdf-viewer' ),
				'print'          => __( 'Print', 'maxtdesign-pdf-viewer' ),
				'fullscreen'     => __( 'Fullscreen', 'maxtdesign-pdf-viewer' ),
				'exitFullscreen' => __( 'Exit fullscreen', 'maxtdesign-pdf-viewer' ),
			),
		);
	}
}

