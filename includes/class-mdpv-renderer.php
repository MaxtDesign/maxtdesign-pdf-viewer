<?php
/**
 * Frontend Renderer
 *
 * Generates HTML output for PDF viewer display.
 * Focuses on zero-CLS rendering with server-side preview images.
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
 * Frontend Renderer Class
 *
 * Handles generation of HTML output for PDF viewer embeds with server-rendered previews.
 */
class Renderer {

	/**
	 * Render PDF viewer HTML
	 *
	 * Generates the complete HTML structure for displaying a PDF.
	 * Uses server-rendered preview for instant display with zero layout shift.
	 *
	 * @param array $attributes {
	 *     Display attributes.
	 *
	 *     @type int    $pdfId          Attachment ID of the PDF.
	 *     @type string $width          CSS width value. Default '100%'.
	 *     @type string $loadBehavior   When to load viewer: 'click', 'visible', 'immediate'. Default 'click'.
	 *     @type bool   $showToolbar    Whether to show toolbar. Default true.
	 *     @type bool   $showDownload   Whether to show download button. Default true.
	 *     @type bool   $showPrint      Whether to show print button. Default true.
	 *     @type bool   $showFullscreen Whether to show fullscreen button. Default true.
	 * }
	 * @return string HTML output.
	 */
	public static function render( array $attributes ): string {
		$attachment_id = $attributes['pdfId'] ?? 0;

		if ( ! $attachment_id ) {
			return self::render_error( __( 'No PDF selected.', 'maxtdesign-pdf-viewer' ) );
		}

		// Verify attachment exists and is a PDF
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return self::render_error( __( 'PDF not found.', 'maxtdesign-pdf-viewer' ) );
		}

		$mime_type = get_post_mime_type( $attachment_id );
		if ( 'application/pdf' !== $mime_type ) {
			return self::render_error( __( 'Invalid file type. PDF required.', 'maxtdesign-pdf-viewer' ) );
		}

		// Get plugin services
		$plugin   = Plugin::instance();
		$cache    = $plugin->get_cache();
		$settings = $plugin->get_settings();

		// Get PDF metadata
		$page_count  = (int) get_post_meta( $attachment_id, '_mdpv_page_count', true ) ?: 1;
		$pdf_width   = (int) get_post_meta( $attachment_id, '_mdpv_width', true ) ?: 612;
		$pdf_height  = (int) get_post_meta( $attachment_id, '_mdpv_height', true ) ?: 792;
		$preview_url = $cache->get_preview_url( $attachment_id );
		$pdf_url     = wp_get_attachment_url( $attachment_id );
		$pdf_title   = get_the_title( $attachment_id );

		// Get current admin settings as defaults
		$default_load_behavior = $settings->get( 'default_load_behavior', 'click' );
		$default_width         = $settings->get( 'default_width', '100%' );
		$default_download       = (bool) $settings->get( 'toolbar_download', true );
		$default_print          = (bool) $settings->get( 'toolbar_print', true );
		$default_fullscreen     = (bool) $settings->get( 'toolbar_fullscreen', true );

		// Merge attributes with defaults from settings
		// Block attributes always take precedence when explicitly set.
		// Admin settings are only used when block attributes are not set (null/undefined).
		$load_behavior = isset( $attributes['loadBehavior'] ) 
			? $attributes['loadBehavior'] 
			: $default_load_behavior;
		
		$show_toolbar = $attributes['showToolbar'] ?? true;
		
		$custom_width = isset( $attributes['width'] ) && ! empty( $attributes['width'] )
			? $attributes['width']
			: $default_width;

		// For toolbar buttons: Block attributes always take precedence when they exist.
		// Admin settings are only used as fallback when attributes are not set.
		// Ensure we properly cast to boolean to handle string "true"/"false" from JSON.
		if ( array_key_exists( 'showDownload', $attributes ) ) {
			$show_download = filter_var( $attributes['showDownload'], FILTER_VALIDATE_BOOLEAN );
		} else {
			$show_download = $default_download;
		}
		
		if ( array_key_exists( 'showPrint', $attributes ) ) {
			$show_print = filter_var( $attributes['showPrint'], FILTER_VALIDATE_BOOLEAN );
		} else {
			$show_print = $default_print;
		}
		
		if ( array_key_exists( 'showFullscreen', $attributes ) ) {
			$show_fullscreen = filter_var( $attributes['showFullscreen'], FILTER_VALIDATE_BOOLEAN );
		} else {
			$show_fullscreen = $default_fullscreen;
		}

		// Build the HTML
		return self::build_viewer_html(
			$attachment_id,
			$pdf_url,
			$pdf_title,
			$preview_url,
			$page_count,
			$pdf_width,
			$pdf_height,
			$custom_width,
			$load_behavior,
			$show_toolbar,
			$show_download,
			$show_print,
			$show_fullscreen
		);
	}

	/**
	 * Build viewer HTML structure
	 *
	 * @param int         $attachment_id  Attachment ID.
	 * @param string      $pdf_url        URL to PDF file.
	 * @param string      $pdf_title      PDF title for accessibility.
	 * @param string|null $preview_url    URL to preview image.
	 * @param int         $page_count     Total page count.
	 * @param int         $pdf_width      PDF width in points.
	 * @param int         $pdf_height     PDF height in points.
	 * @param string      $custom_width   CSS width value.
	 * @param string      $load_behavior  Load behavior setting.
	 * @param bool        $show_toolbar   Show toolbar flag.
	 * @param bool        $show_download  Show download button flag.
	 * @param bool        $show_print     Show print button flag.
	 * @param bool        $show_fullscreen Show fullscreen button flag.
	 * @return string HTML output.
	 */
	private static function build_viewer_html(
		int $attachment_id,
		string $pdf_url,
		string $pdf_title,
		?string $preview_url,
		int $page_count,
		int $pdf_width,
		int $pdf_height,
		string $custom_width,
		string $load_behavior,
		bool $show_toolbar,
		bool $show_download,
		bool $show_print,
		bool $show_fullscreen
	): string {
		// Calculate aspect ratio for zero CLS
		$aspect_ratio = $pdf_width . ' / ' . $pdf_height;

		// Build data attributes for JavaScript
		$data_attrs = self::build_data_attributes(
			$attachment_id,
			$pdf_url,
			$page_count,
			$load_behavior,
			$show_toolbar,
			$show_download,
			$show_print,
			$show_fullscreen
		);

		// Build inline styles
		$styles = self::build_inline_styles( $aspect_ratio, $custom_width );

		// Generate unique ID for accessibility
		$viewer_id = 'mdpv-viewer-' . $attachment_id;

		ob_start();
		?>
		<div 
			id="<?php echo esc_attr( $viewer_id ); ?>"
			class="mdpv-viewer"
			<?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in build_data_attributes ?>
			style="<?php echo esc_attr( $styles ); ?>"
			role="document"
			aria-label="<?php echo esc_attr( sprintf( 
				/* translators: %s: PDF document title */
				__( 'PDF Document: %s', 'maxtdesign-pdf-viewer' ), 
				$pdf_title 
			) ); ?>"
		>
			<?php if ( $preview_url ) : ?>
				<img 
					src="<?php echo esc_url( $preview_url ); ?>" 
					alt="<?php echo esc_attr( sprintf( 
						/* translators: 1: PDF title, 2: page count */
						__( '%1$s - Page 1 of %2$d', 'maxtdesign-pdf-viewer' ), 
						$pdf_title, 
						$page_count 
					) ); ?>"
					class="mdpv-preview"
					loading="eager"
					decoding="async"
					fetchpriority="high"
				>
			<?php else : ?>
				<?php echo self::render_placeholder( $pdf_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>

			<?php if ( 'immediate' !== $load_behavior ) : ?>
				<button 
					class="mdpv-activate" 
					type="button" 
					aria-label="<?php echo esc_attr( sprintf(
						/* translators: 1: PDF title */
						__( 'Open interactive viewer for %s', 'maxtdesign-pdf-viewer' ),
						$pdf_title
					) ); ?>"
					aria-controls="<?php echo esc_attr( $viewer_id ); ?>"
				>
					<svg class="mdpv-activate-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
						<path d="M8 5v14l11-7z"/>
					</svg>
					<span class="mdpv-activate-text">
						<?php
						printf(
							/* translators: %d: Number of pages */
							esc_html__( 'View Document (%d pages)', 'maxtdesign-pdf-viewer' ),
							esc_html( (string) $page_count )
						);
						?>
					</span>
				</button>
			<?php endif; ?>

			<div class="mdpv-loading" aria-live="polite" hidden>
				<div class="mdpv-spinner" aria-hidden="true"></div>
				<span><?php esc_html_e( 'Loading document...', 'maxtdesign-pdf-viewer' ); ?></span>
			</div>

			<div class="mdpv-error-container" role="alert" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build data attributes string
	 *
	 * @param int    $attachment_id  Attachment ID.
	 * @param string $pdf_url        PDF URL.
	 * @param int    $page_count     Page count.
	 * @param string $load_behavior  Load behavior.
	 * @param bool   $show_toolbar   Show toolbar.
	 * @param bool   $show_download  Show download.
	 * @param bool   $show_print     Show print.
	 * @param bool   $show_fullscreen Show fullscreen.
	 * @return string HTML attributes string.
	 */
	private static function build_data_attributes(
		int $attachment_id,
		string $pdf_url,
		int $page_count,
		string $load_behavior,
		bool $show_toolbar,
		bool $show_download,
		bool $show_print,
		bool $show_fullscreen
	): string {
		$attrs = [
			'data-pdf-id'         => $attachment_id,
			'data-pdf-url'        => esc_url( $pdf_url ),
			'data-pdf-pages'      => $page_count,
			'data-load'           => esc_attr( $load_behavior ),
			'data-toolbar'        => $show_toolbar ? 'true' : 'false',
			'data-download'       => $show_download ? 'true' : 'false',
			'data-print'          => $show_print ? 'true' : 'false',
			'data-fullscreen'     => $show_fullscreen ? 'true' : 'false',
		];

		$output = '';
		foreach ( $attrs as $name => $value ) {
			$output .= sprintf( ' %s="%s"', $name, $value );
		}

		return $output;
	}

	/**
	 * Build inline styles string
	 *
	 * @param string $aspect_ratio CSS aspect-ratio value.
	 * @param string $custom_width CSS width value.
	 * @return string CSS styles string.
	 */
	private static function build_inline_styles( string $aspect_ratio, string $custom_width ): string {
		$styles = [
			'aspect-ratio' => $aspect_ratio,
			'max-width'    => $custom_width,
			'width'        => '100%',
		];

		$output = '';
		foreach ( $styles as $property => $value ) {
			$output .= sprintf( '%s: %s; ', $property, $value );
		}

		return trim( $output );
	}

	/**
	 * Render placeholder when preview not available
	 *
	 * @param string $title PDF title.
	 * @return string HTML output.
	 */
	private static function render_placeholder( string $title ): string {
		ob_start();
		?>
		<div class="mdpv-placeholder">
			<svg class="mdpv-placeholder-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
				<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-linecap="round" stroke-linejoin="round"/>
				<polyline points="14,2 14,8 20,8" stroke-linecap="round" stroke-linejoin="round"/>
				<line x1="16" y1="13" x2="8" y2="13" stroke-linecap="round"/>
				<line x1="16" y1="17" x2="8" y2="17" stroke-linecap="round"/>
				<line x1="10" y1="9" x2="8" y2="9" stroke-linecap="round"/>
			</svg>
			<span class="mdpv-placeholder-text"><?php echo esc_html( $title ); ?></span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render error message
	 *
	 * @param string $message Error message.
	 * @return string HTML output.
	 */
	private static function render_error( string $message ): string {
		return sprintf(
			'<div class="mdpv-error" role="alert"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Handle shortcode rendering
	 *
	 * Usage: [pdf_viewer id="123" width="800px" load="click"]
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content (unused).
	 * @return string HTML output.
	 */
	public static function shortcode_handler( $atts, $content = '' ): string {
		$atts = shortcode_atts(
			[
				'id'         => 0,
				'width'      => '',
				'load'       => '',
				'toolbar'    => 'true',
				'download'   => '',
				'print'      => '',
				'fullscreen' => '',
			],
			$atts,
			'pdf_viewer'
		);

		// Convert to render attributes format
		$attributes = [
			'pdfId' => absint( $atts['id'] ),
		];

		if ( ! empty( $atts['width'] ) ) {
			$attributes['width'] = sanitize_text_field( $atts['width'] );
		}

		if ( ! empty( $atts['load'] ) ) {
			$attributes['loadBehavior'] = sanitize_key( $atts['load'] );
		}

		if ( 'false' === $atts['toolbar'] ) {
			$attributes['showToolbar'] = false;
		}

		if ( ! empty( $atts['download'] ) ) {
			$attributes['showDownload'] = 'true' === $atts['download'];
		}

		if ( ! empty( $atts['print'] ) ) {
			$attributes['showPrint'] = 'true' === $atts['print'];
		}

		if ( ! empty( $atts['fullscreen'] ) ) {
			$attributes['showFullscreen'] = 'true' === $atts['fullscreen'];
		}

		return self::render( $attributes );
	}
}

