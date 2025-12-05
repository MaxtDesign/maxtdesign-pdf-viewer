<?php
/**
 * Gutenberg Block Registration
 *
 * Handles registration and setup of the PDF Viewer Gutenberg block.
 *
 * @package MaxtDesign\PDFViewer
 * @since 1.0.0
 */

declare(strict_types=1);

namespace MaxtDesign\PDFViewer;

/**
 * Block registration class
 */
final class Block {

	/**
	 * Block name
	 */
	public const BLOCK_NAME = 'maxtdesign/pdf-viewer';

	/**
	 * Register the block
	 *
	 * @return void
	 */
	public static function register(): void {
		// Check if blocks directory exists
		$block_dir = MDPV_PLUGIN_DIR . 'blocks/pdf-viewer';

		if ( ! file_exists( $block_dir . '/block.json' ) ) {
			return;
		}

		// Register the block
		register_block_type( $block_dir );

		// Add editor assets
		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_editor_assets' ] );
	}

	/**
	 * Enqueue block editor assets
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets(): void {
		// Localize script with data for the editor
		wp_localize_script(
			'maxtdesign-pdf-viewer-editor-script', // Handle from block.json editorScript
			'mdpvBlockData',
			self::get_editor_data()
		);
	}

	/**
	 * Get data for the block editor
	 *
	 * @return array Editor data.
	 */
	private static function get_editor_data(): array {
		$plugin   = Plugin::instance();
		$settings = $plugin->get_settings();

		return [
			'pluginUrl'    => MDPV_PLUGIN_URL,
			'restUrl'      => rest_url( 'mdpv/v1' ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'defaults'     => [
				'loadBehavior'   => $settings->get( 'default_load_behavior', 'click' ),
				'width'          => $settings->get( 'default_width', '100%' ),
				'showDownload'   => $settings->get( 'toolbar_download', true ),
				'showPrint'      => $settings->get( 'toolbar_print', true ),
				'showFullscreen' => $settings->get( 'toolbar_fullscreen', true ),
			],
			'i18n'         => [
				'title'            => __( 'PDF Viewer', 'maxtdesign-pdf-viewer' ),
				'description'      => __( 'Embed PDFs with instant preview and zero layout shift.', 'maxtdesign-pdf-viewer' ),
				'selectPdf'        => __( 'Select PDF', 'maxtdesign-pdf-viewer' ),
				'replacePdf'       => __( 'Replace', 'maxtdesign-pdf-viewer' ),
				'removePdf'        => __( 'Remove', 'maxtdesign-pdf-viewer' ),
				'pdfSettings'      => __( 'PDF Settings', 'maxtdesign-pdf-viewer' ),
				'displaySettings'  => __( 'Display Settings', 'maxtdesign-pdf-viewer' ),
				'toolbarSettings'  => __( 'Toolbar Options', 'maxtdesign-pdf-viewer' ),
				'width'            => __( 'Width', 'maxtdesign-pdf-viewer' ),
				'widthHelp'        => __( 'CSS value: 100%, 800px, etc.', 'maxtdesign-pdf-viewer' ),
				'loadBehavior'     => __( 'Load Behavior', 'maxtdesign-pdf-viewer' ),
				'loadBehaviorHelp' => __( 'When to load the interactive viewer', 'maxtdesign-pdf-viewer' ),
				'onClick'          => __( 'On Click', 'maxtdesign-pdf-viewer' ),
				'whenVisible'      => __( 'When Visible', 'maxtdesign-pdf-viewer' ),
				'immediately'      => __( 'Immediately', 'maxtdesign-pdf-viewer' ),
				'showToolbar'      => __( 'Show Toolbar', 'maxtdesign-pdf-viewer' ),
				'downloadButton'   => __( 'Download Button', 'maxtdesign-pdf-viewer' ),
				'printButton'      => __( 'Print Button', 'maxtdesign-pdf-viewer' ),
				'fullscreenButton' => __( 'Fullscreen Button', 'maxtdesign-pdf-viewer' ),
				'noPreview'        => __( 'Preview not available', 'maxtdesign-pdf-viewer' ),
				'pages'            => __( 'pages', 'maxtdesign-pdf-viewer' ),
				'uploadPdf'        => __( 'Upload a PDF or select one from your media library.', 'maxtdesign-pdf-viewer' ),
			],
		];
	}

	/**
	 * Check if current post has our block
	 *
	 * @param int|null $post_id Post ID to check.
	 * @return bool True if post has block.
	 */
	public static function post_has_block( ?int $post_id = null ): bool {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		return has_block( self::BLOCK_NAME, $post );
	}
}

