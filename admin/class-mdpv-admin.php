<?php
/**
 * Admin Settings Page
 *
 * Handles the plugin's admin settings interface.
 *
 * @package MaxtDesign\PDFViewer
 * @since 1.0.0
 */

declare(strict_types=1);

namespace MaxtDesign\PDFViewer;

// Security check - exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Admin class
 */
final class Admin {

	/**
	 * Settings page slug
	 */
	public const PAGE_SLUG = 'mdpv-settings';

	/**
	 * Settings group
	 */
	public const SETTINGS_GROUP = 'mdpv_settings_group';

	/**
	 * Plugin instance
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Settings instance
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin   = $plugin;
		$this->settings = $plugin->get_settings();
	}

	/**
	 * Initialize admin hooks
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// AJAX handlers
		add_action( 'wp_ajax_mdpv_bulk_process', [ $this, 'ajax_bulk_process' ] );
		add_action( 'wp_ajax_mdpv_clear_cache', [ $this, 'ajax_clear_cache' ] );
		add_action( 'wp_ajax_mdpv_get_stats', [ $this, 'ajax_get_stats' ] );
		add_action( 'wp_ajax_mdpv_refresh_capabilities', [ $this, 'ajax_refresh_capabilities' ] );
	}

	/**
	 * Add settings page to admin menu
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'PDF Viewer Settings', 'maxtdesign-pdf-viewer' ),
			__( 'PDF Viewer', 'maxtdesign-pdf-viewer' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings with WordPress Settings API
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			'mdpv_settings',
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this->settings, 'sanitize_all' ],
			]
		);

		// General Settings Section
		add_settings_section(
			'mdpv_general_section',
			__( 'General Settings', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_general_section_description' ],
			self::PAGE_SLUG
		);

		// Generate on Upload
		add_settings_field(
			'generate_on_upload',
			__( 'Auto-Generate Previews', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_checkbox_field' ],
			self::PAGE_SLUG,
			'mdpv_general_section',
			[
				'id'          => 'generate_on_upload',
				'description' => __( 'Automatically generate preview images when PDFs are uploaded.', 'maxtdesign-pdf-viewer' ),
			]
		);

		// Preview Quality
		add_settings_field(
			'preview_quality',
			__( 'Preview Quality', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_select_field' ],
			self::PAGE_SLUG,
			'mdpv_general_section',
			[
				'id'          => 'preview_quality',
				'options'     => [
					'low'    => __( 'Low (72 DPI) - Smallest file size', 'maxtdesign-pdf-viewer' ),
					'medium' => __( 'Medium (150 DPI) - Balanced', 'maxtdesign-pdf-viewer' ),
					'high'   => __( 'High (300 DPI) - Best quality', 'maxtdesign-pdf-viewer' ),
				],
				'description' => __( 'Higher quality means larger preview files but sharper images.', 'maxtdesign-pdf-viewer' ),
			]
		);

		// Display Settings Section
		add_settings_section(
			'mdpv_display_section',
			__( 'Display Defaults', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_display_section_description' ],
			self::PAGE_SLUG
		);

		// Default Load Behavior
		add_settings_field(
			'default_load_behavior',
			__( 'Load Behavior', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_select_field' ],
			self::PAGE_SLUG,
			'mdpv_display_section',
			[
				'id'          => 'default_load_behavior',
				'options'     => [
					'click'     => __( 'On Click - Load when user clicks', 'maxtdesign-pdf-viewer' ),
					'visible'   => __( 'When Visible - Load when scrolled into view', 'maxtdesign-pdf-viewer' ),
					'immediate' => __( 'Immediately - Load on page load', 'maxtdesign-pdf-viewer' ),
				],
				'description' => __( 'When the interactive PDF viewer should load. "On Click" is best for performance.', 'maxtdesign-pdf-viewer' ),
			]
		);

		// Default Width
		add_settings_field(
			'default_width',
			__( 'Default Width', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_text_field' ],
			self::PAGE_SLUG,
			'mdpv_display_section',
			[
				'id'          => 'default_width',
				'description' => __( 'CSS width value (e.g., 100%, 800px, 50vw).', 'maxtdesign-pdf-viewer' ),
				'placeholder' => '100%',
			]
		);

		// Toolbar Settings Section
		add_settings_section(
			'mdpv_toolbar_section',
			__( 'Toolbar Options', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_toolbar_section_description' ],
			self::PAGE_SLUG
		);

		// Toolbar Download
		add_settings_field(
			'toolbar_download',
			__( 'Download Button', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_checkbox_field' ],
			self::PAGE_SLUG,
			'mdpv_toolbar_section',
			[
				'id'          => 'toolbar_download',
				'description' => __( 'Show download button in the viewer toolbar.', 'maxtdesign-pdf-viewer' ),
			]
		);

		// Toolbar Print
		add_settings_field(
			'toolbar_print',
			__( 'Print Button', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_checkbox_field' ],
			self::PAGE_SLUG,
			'mdpv_toolbar_section',
			[
				'id'          => 'toolbar_print',
				'description' => __( 'Show print button in the viewer toolbar.', 'maxtdesign-pdf-viewer' ),
			]
		);

		// Toolbar Fullscreen
		add_settings_field(
			'toolbar_fullscreen',
			__( 'Fullscreen Button', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_checkbox_field' ],
			self::PAGE_SLUG,
			'mdpv_toolbar_section',
			[
				'id'          => 'toolbar_fullscreen',
				'description' => __( 'Show fullscreen button in the viewer toolbar.', 'maxtdesign-pdf-viewer' ),
			]
		);

		// Cache Settings Section
		add_settings_section(
			'mdpv_cache_section',
			__( 'Cache Settings', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_cache_section_description' ],
			self::PAGE_SLUG
		);

		// Cache Duration
		add_settings_field(
			'cache_duration',
			__( 'Cache Duration', 'maxtdesign-pdf-viewer' ),
			[ $this, 'render_number_field' ],
			self::PAGE_SLUG,
			'mdpv_cache_section',
			[
				'id'          => 'cache_duration',
				'min'         => 1,
				'max'         => 365,
				'description' => __( 'Days to keep orphaned preview images before cleanup.', 'maxtdesign-pdf-viewer' ),
			]
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on our settings page
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mdpv-admin',
			MDPV_PLUGIN_URL . 'admin/css/mdpv-admin.css',
			[],
			MDPV_VERSION
		);

		wp_enqueue_script(
			'mdpv-admin',
			MDPV_PLUGIN_URL . 'admin/js/mdpv-admin.js',
			[ 'jquery' ],
			MDPV_VERSION,
			true
		);

		wp_localize_script(
			'mdpv-admin',
			'mdpvAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mdpv_admin_nonce' ),
				'i18n'    => [
					'processing'    => __( 'Processing...', 'maxtdesign-pdf-viewer' ),
					'clearing'      => __( 'Clearing...', 'maxtdesign-pdf-viewer' ),
					'success'       => __( 'Success!', 'maxtdesign-pdf-viewer' ),
					'error'         => __( 'An error occurred.', 'maxtdesign-pdf-viewer' ),
				'confirmClear'  => __( 'Are you sure you want to clear all cached previews? They will be regenerated as needed.', 'maxtdesign-pdf-viewer' ),
				/* translators: %d: Number of processed PDFs */
				'processed'     => __( 'Processed %d PDFs', 'maxtdesign-pdf-viewer' ),
				/* translators: %d: Number of failed PDFs */
				'failed'        => __( '%d failed', 'maxtdesign-pdf-viewer' ),
				/* translators: %d: Number of remaining PDFs */
				'remaining'     => __( '%d remaining', 'maxtdesign-pdf-viewer' ),
				'cacheCleared'  => __( 'Cache cleared successfully.', 'maxtdesign-pdf-viewer' ),
				/* translators: %d: Number of deleted files */
				'filesDeleted'  => __( '%d files deleted.', 'maxtdesign-pdf-viewer' ),
				],
			]
		);
	}

	/**
	 * Render the settings page
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab parameter is for display only, not processing
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap mdpv-admin-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper mdpv-nav-tabs">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=general' ) ); ?>" 
				   class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'maxtdesign-pdf-viewer' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=server' ) ); ?>" 
				   class="nav-tab <?php echo 'server' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Server Info', 'maxtdesign-pdf-viewer' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=tools' ) ); ?>" 
				   class="nav-tab <?php echo 'tools' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Tools', 'maxtdesign-pdf-viewer' ); ?>
				</a>
			</nav>

			<div class="mdpv-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'server':
						$this->render_server_tab();
						break;
					case 'tools':
						$this->render_tools_tab();
						break;
					default:
						$this->render_general_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render general settings tab
	 *
	 * @return void
	 */
	private function render_general_tab(): void {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( self::SETTINGS_GROUP );
			do_settings_sections( self::PAGE_SLUG );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render server info tab
	 *
	 * @return void
	 */
	private function render_server_tab(): void {
		$compatibility = $this->plugin->get_compatibility();
		$capabilities  = $compatibility->get_capabilities();
		$recommended   = $compatibility->get_recommended_method();
		$webp_support = $capabilities['gd_webp'];
		?>
		<div class="mdpv-server-info">
			<h2><?php esc_html_e( 'Server Capabilities', 'maxtdesign-pdf-viewer' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'These capabilities determine how PDF previews are generated on your server.', 'maxtdesign-pdf-viewer' ); ?>
			</p>

			<table class="widefat mdpv-capabilities-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Feature', 'maxtdesign-pdf-viewer' ); ?></th>
						<th><?php esc_html_e( 'Status', 'maxtdesign-pdf-viewer' ); ?></th>
						<th><?php esc_html_e( 'Notes', 'maxtdesign-pdf-viewer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'ImageMagick', 'maxtdesign-pdf-viewer' ); ?></strong></td>
						<td>
							<?php if ( $capabilities['imagemagick'] ) : ?>
								<span class="mdpv-status mdpv-status-success">✓ <?php esc_html_e( 'Available', 'maxtdesign-pdf-viewer' ); ?></span>
							<?php else : ?>
								<span class="mdpv-status mdpv-status-error">✗ <?php esc_html_e( 'Not Available', 'maxtdesign-pdf-viewer' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( $capabilities['imagemagick'] ) {
								esc_html_e( 'Best method for PDF preview generation.', 'maxtdesign-pdf-viewer' );
							} else {
								esc_html_e( 'Install ImageMagick with PDF support for best results.', 'maxtdesign-pdf-viewer' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'WebP Support', 'maxtdesign-pdf-viewer' ); ?></strong></td>
						<td>
							<?php if ( $webp_support ) : ?>
								<span class="mdpv-status mdpv-status-success">✓ <?php esc_html_e( 'Available', 'maxtdesign-pdf-viewer' ); ?></span>
							<?php else : ?>
								<span class="mdpv-status mdpv-status-warning">✗ <?php esc_html_e( 'Not Available', 'maxtdesign-pdf-viewer' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( $webp_support ) {
								esc_html_e( 'Previews will be saved as WebP for smaller file sizes.', 'maxtdesign-pdf-viewer' );
							} else {
								esc_html_e( 'Previews will fall back to JPEG format.', 'maxtdesign-pdf-viewer' );
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="mdpv-recommended-method">
				<h3><?php esc_html_e( 'Recommended Extraction Method', 'maxtdesign-pdf-viewer' ); ?></h3>
				<?php if ( ! empty( $recommended ) && 'none' !== $recommended ) : ?>
					<p class="mdpv-method-badge mdpv-method-<?php echo esc_attr( $recommended ); ?>">
						<?php esc_html_e( '✓ ImageMagick (Best)', 'maxtdesign-pdf-viewer' ); ?>
					</p>
				<?php else : ?>
					<p class="mdpv-method-badge mdpv-method-none">
						<?php esc_html_e( '✗ No extraction method available', 'maxtdesign-pdf-viewer' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Please install the ImageMagick PHP extension with PDF support to enable PDF preview generation.', 'maxtdesign-pdf-viewer' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="mdpv-refresh-capabilities">
				<button type="button" class="button" id="mdpv-refresh-capabilities">
					<?php esc_html_e( 'Refresh Capabilities', 'maxtdesign-pdf-viewer' ); ?>
				</button>
				<span class="spinner"></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render tools tab
	 *
	 * @return void
	 */
	private function render_tools_tab(): void {
		$cache       = $this->plugin->get_cache();
		$cache_stats = $this->get_cache_stats();
		$pdf_stats   = $this->get_pdf_stats();
		?>
		<div class="mdpv-tools">
			<!-- Bulk Processing -->
			<div class="mdpv-tool-card">
				<h2><?php esc_html_e( 'Bulk Process PDFs', 'maxtdesign-pdf-viewer' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Generate preview images for PDFs that haven\'t been processed yet.', 'maxtdesign-pdf-viewer' ); ?>
				</p>

				<div class="mdpv-stats-grid">
					<div class="mdpv-stat">
						<span class="mdpv-stat-value" id="mdpv-total-pdfs"><?php echo esc_html( $pdf_stats['total'] ); ?></span>
						<span class="mdpv-stat-label"><?php esc_html_e( 'Total PDFs', 'maxtdesign-pdf-viewer' ); ?></span>
					</div>
					<div class="mdpv-stat">
						<span class="mdpv-stat-value" id="mdpv-processed-pdfs"><?php echo esc_html( $pdf_stats['processed'] ); ?></span>
						<span class="mdpv-stat-label"><?php esc_html_e( 'Processed', 'maxtdesign-pdf-viewer' ); ?></span>
					</div>
					<div class="mdpv-stat">
						<span class="mdpv-stat-value" id="mdpv-unprocessed-pdfs"><?php echo esc_html( $pdf_stats['unprocessed'] ); ?></span>
						<span class="mdpv-stat-label"><?php esc_html_e( 'Unprocessed', 'maxtdesign-pdf-viewer' ); ?></span>
					</div>
				</div>

				<div class="mdpv-bulk-actions">
					<button type="button" class="button button-primary" id="mdpv-bulk-process" 
							<?php disabled( $pdf_stats['unprocessed'], 0 ); ?>>
						<?php esc_html_e( 'Process Unprocessed PDFs', 'maxtdesign-pdf-viewer' ); ?>
					</button>
					<span class="spinner"></span>
				</div>

				<div class="mdpv-progress-container" style="display: none;">
					<div class="mdpv-progress-bar">
						<div class="mdpv-progress-fill"></div>
					</div>
					<div class="mdpv-progress-text"></div>
				</div>
			</div>

			<!-- Cache Management -->
			<div class="mdpv-tool-card">
				<h2><?php esc_html_e( 'Cache Management', 'maxtdesign-pdf-viewer' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Manage the preview image cache.', 'maxtdesign-pdf-viewer' ); ?>
				</p>

				<div class="mdpv-stats-grid">
					<div class="mdpv-stat">
						<span class="mdpv-stat-value" id="mdpv-cache-files"><?php echo esc_html( $cache_stats['file_count'] ); ?></span>
						<span class="mdpv-stat-label"><?php esc_html_e( 'Cached Files', 'maxtdesign-pdf-viewer' ); ?></span>
					</div>
					<div class="mdpv-stat">
						<span class="mdpv-stat-value" id="mdpv-cache-size"><?php echo esc_html( $cache_stats['size_formatted'] ); ?></span>
						<span class="mdpv-stat-label"><?php esc_html_e( 'Cache Size', 'maxtdesign-pdf-viewer' ); ?></span>
					</div>
				</div>

				<div class="mdpv-cache-actions">
					<button type="button" class="button" id="mdpv-clear-cache">
						<?php esc_html_e( 'Clear All Previews', 'maxtdesign-pdf-viewer' ); ?>
					</button>
					<span class="spinner"></span>
				</div>

				<p class="description">
					<?php esc_html_e( 'Note: Clearing the cache will remove all generated preview images. They will be regenerated when PDFs are viewed or re-processed.', 'maxtdesign-pdf-viewer' ); ?>
				</p>
			</div>

			<!-- Shortcode Reference -->
			<div class="mdpv-tool-card">
				<h2><?php esc_html_e( 'Shortcode Reference', 'maxtdesign-pdf-viewer' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Use this shortcode to embed PDFs in classic editor or widgets.', 'maxtdesign-pdf-viewer' ); ?>
				</p>

				<div class="mdpv-shortcode-example">
					<code>[pdf_viewer id="123"]</code>
				</div>

				<h4><?php esc_html_e( 'Available Attributes', 'maxtdesign-pdf-viewer' ); ?></h4>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Attribute', 'maxtdesign-pdf-viewer' ); ?></th>
							<th><?php esc_html_e( 'Default', 'maxtdesign-pdf-viewer' ); ?></th>
							<th><?php esc_html_e( 'Description', 'maxtdesign-pdf-viewer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>id</code></td>
							<td><?php esc_html_e( '(required)', 'maxtdesign-pdf-viewer' ); ?></td>
							<td><?php esc_html_e( 'Attachment ID of the PDF', 'maxtdesign-pdf-viewer' ); ?></td>
						</tr>
						<tr>
							<td><code>width</code></td>
							<td><code>100%</code></td>
							<td><?php esc_html_e( 'CSS width value', 'maxtdesign-pdf-viewer' ); ?></td>
						</tr>
						<tr>
							<td><code>load</code></td>
							<td><code>click</code></td>
							<td><?php esc_html_e( 'click, visible, or immediate', 'maxtdesign-pdf-viewer' ); ?></td>
						</tr>
						<tr>
							<td><code>toolbar</code></td>
							<td><code>true</code></td>
							<td><?php esc_html_e( 'Show/hide toolbar', 'maxtdesign-pdf-viewer' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	// Section description callbacks
	public function render_general_section_description(): void {
		echo '<p>' . esc_html__( 'Configure how PDF previews are generated.', 'maxtdesign-pdf-viewer' ) . '</p>';
	}

	public function render_display_section_description(): void {
		echo '<p>' . esc_html__( 'Default display settings for PDF viewers. These can be overridden per block/shortcode.', 'maxtdesign-pdf-viewer' ) . '</p>';
	}

	public function render_toolbar_section_description(): void {
		echo '<p>' . esc_html__( 'Choose which buttons appear in the viewer toolbar by default.', 'maxtdesign-pdf-viewer' ) . '</p>';
	}

	public function render_cache_section_description(): void {
		echo '<p>' . esc_html__( 'Configure preview image caching behavior.', 'maxtdesign-pdf-viewer' ) . '</p>';
	}

	// Field rendering callbacks
	public function render_checkbox_field( array $args ): void {
		$id    = $args['id'];
		$value = $this->settings->get( $id );
		?>
		<label>
			<input type="checkbox" 
				   name="mdpv_settings[<?php echo esc_attr( $id ); ?>]" 
				   value="1" 
				   <?php checked( $value, true ); ?>>
			<?php echo esc_html( $args['description'] ?? '' ); ?>
		</label>
		<?php
	}

	public function render_select_field( array $args ): void {
		$id      = $args['id'];
		$value   = $this->settings->get( $id );
		$options = $args['options'] ?? [];
		?>
		<select name="mdpv_settings[<?php echo esc_attr( $id ); ?>]">
			<?php foreach ( $options as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
					<?php echo esc_html( $option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	public function render_text_field( array $args ): void {
		$id    = $args['id'];
		$value = $this->settings->get( $id );
		?>
		<input type="text" 
			   name="mdpv_settings[<?php echo esc_attr( $id ); ?>]" 
			   value="<?php echo esc_attr( $value ); ?>" 
			   class="regular-text"
			   placeholder="<?php echo esc_attr( $args['placeholder'] ?? '' ); ?>">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	public function render_number_field( array $args ): void {
		$id    = $args['id'];
		$value = $this->settings->get( $id );
		?>
		<input type="number" 
			   name="mdpv_settings[<?php echo esc_attr( $id ); ?>]" 
			   value="<?php echo esc_attr( $value ); ?>" 
			   class="small-text"
			   min="<?php echo esc_attr( $args['min'] ?? '' ); ?>"
			   max="<?php echo esc_attr( $args['max'] ?? '' ); ?>">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Cache stats.
	 */
	private function get_cache_stats(): array {
		$cache     = $this->plugin->get_cache();
		$cache_dir = $cache->get_cache_directory();
		
		$file_count = 0;
		$total_size = 0;

		if ( is_dir( $cache_dir ) ) {
			$files = glob( $cache_dir . '*.webp' );
			if ( $files ) {
				$file_count = count( $files );
				foreach ( $files as $file ) {
					$total_size += filesize( $file );
				}
			}
		}

		return [
			'file_count'     => $file_count,
			'total_size'     => $total_size,
			'size_formatted' => size_format( $total_size ),
		];
	}

	/**
	 * Get PDF statistics
	 *
	 * @return array PDF stats.
	 */
	private function get_pdf_stats(): array {
		global $wpdb;

		// Total PDFs
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats query, caching not needed
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type = 'application/pdf'"
		);

		// Processed PDFs
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats query, caching not needed
		$processed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'attachment' 
				AND p.post_mime_type = 'application/pdf'
				AND pm.meta_key = %s 
				AND pm.meta_value = '1'",
				'_mdpv_processed'
			)
		);

		return [
			'total'       => $total,
			'processed'   => $processed,
			'unprocessed' => $total - $processed,
		];
	}

	/**
	 * AJAX: Bulk process PDFs
	 *
	 * @return void
	 */
	public function ajax_bulk_process(): void {
		check_ajax_referer( 'mdpv_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'maxtdesign-pdf-viewer' ) ] );
		}

		$extractor = $this->plugin->get_extractor();
		$result    = $extractor->bulk_process( 10 ); // Process 10 at a time

		wp_send_json_success( [
			'processed' => $result['processed'],
			'failed'    => $result['failed'],
			'remaining' => $result['remaining'],
		] );
	}

	/**
	 * AJAX: Clear cache
	 *
	 * @return void
	 */
	public function ajax_clear_cache(): void {
		check_ajax_referer( 'mdpv_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'maxtdesign-pdf-viewer' ) ] );
		}

		$cache     = $this->plugin->get_cache();
		$deleted   = $cache->clear_all();

		wp_send_json_success( [ 'deleted' => $deleted ] );
	}

	/**
	 * AJAX: Get stats
	 *
	 * @return void
	 */
	public function ajax_get_stats(): void {
		check_ajax_referer( 'mdpv_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'maxtdesign-pdf-viewer' ) ] );
		}

		wp_send_json_success( [
			'pdf'   => $this->get_pdf_stats(),
			'cache' => $this->get_cache_stats(),
		] );
	}

	/**
	 * AJAX: Refresh capabilities
	 *
	 * @return void
	 */
	public function ajax_refresh_capabilities(): void {
		check_ajax_referer( 'mdpv_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'maxtdesign-pdf-viewer' ) ] );
		}

		// Delete the transient to force refresh
		delete_transient( 'mdpv_server_capabilities' );

		wp_send_json_success();
	}
}

