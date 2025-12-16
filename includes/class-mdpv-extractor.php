<?php
/**
 * PDF Extraction Service
 *
 * Handles server-side PDF processing: metadata extraction and preview generation.
 * Supports ImageMagick (preferred) and Ghostscript (fallback) methods.
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
 * PDF Extraction Service Class
 *
 * Handles server-side PDF processing: metadata extraction and preview generation.
 * Supports ImageMagick (preferred) and Ghostscript (fallback) methods.
 */
class Extractor {

	/**
	 * Quality presets for preview generation
	 *
	 * @var array<string, array{resolution: int, quality: int}>
	 */
	private const QUALITY_PRESETS = [
		'low'    => [ 'resolution' => 72,  'quality' => 70 ],
		'medium' => [ 'resolution' => 150, 'quality' => 85 ],
		'high'   => [ 'resolution' => 300, 'quality' => 95 ],
	];

	/**
	 * Cache service instance
	 *
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Settings service instance
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Compatibility service instance
	 *
	 * @var Compatibility
	 */
	private Compatibility $compatibility;

	/**
	 * Constructor
	 *
	 * @param Cache         $cache         Cache service instance.
	 * @param Settings      $settings      Settings service instance.
	 * @param Compatibility $compatibility Compatibility service instance.
	 */
	public function __construct( Cache $cache, Settings $settings, Compatibility $compatibility ) {
		$this->cache         = $cache;
		$this->settings      = $settings;
		$this->compatibility = $compatibility;
	}

	/**
	 * Process PDF on upload
	 *
	 * Hooked to 'add_attachment' action. Verifies attachment is PDF,
	 * checks if auto-processing is enabled, and processes if conditions are met.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	public function process_upload( int $attachment_id ): void {
		// Verify attachment is PDF
		$mime_type = get_post_mime_type( $attachment_id );
		if ( 'application/pdf' !== $mime_type ) {
			return;
		}

		// Check if auto-processing is enabled
		if ( ! $this->settings->get( 'generate_on_upload', true ) ) {
			return;
		}

		// Process the PDF
		$this->process_pdf( $attachment_id );
	}

	/**
	 * Main orchestration method for PDF processing
	 *
	 * Extracts metadata and generates preview image. Marks attachment as processed
	 * even if preview generation fails (metadata is still useful).
	 *
	 * @param int  $attachment_id Attachment post ID.
	 * @param bool $force         Whether to force reprocessing even if already processed.
	 * @return bool True on success, false on failure.
	 */
	public function process_pdf( int $attachment_id, bool $force = false ): bool {
		// Check if already processed
		if ( ! $force && get_post_meta( $attachment_id, '_mdpv_processed', true ) ) {
			return true;
		}

		$pdf_path = get_attached_file( $attachment_id );

		if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
			$this->log_error( $attachment_id, __( 'PDF file not found', 'maxtdesign-pdf-viewer' ) );
			return false;
		}

		// Extract metadata
		$metadata = $this->extract_metadata( $pdf_path );

		if ( ! $metadata ) {
			$this->log_error( $attachment_id, __( 'Failed to extract metadata', 'maxtdesign-pdf-viewer' ) );
			return false;
		}

		// Save metadata
		update_post_meta( $attachment_id, '_mdpv_page_count', $metadata['page_count'] );
		update_post_meta( $attachment_id, '_mdpv_width', $metadata['width'] );
		update_post_meta( $attachment_id, '_mdpv_height', $metadata['height'] );
		update_post_meta( $attachment_id, '_mdpv_title', $metadata['title'] );
		update_post_meta( $attachment_id, '_mdpv_author', $metadata['author'] );

		// Generate preview
		$preview_result = $this->generate_preview( $attachment_id, $pdf_path );

		if ( ! $preview_result['success'] ) {
			$this->log_error( $attachment_id, $preview_result['error'] ?? __( 'Preview generation failed', 'maxtdesign-pdf-viewer' ) );
			update_post_meta( $attachment_id, '_mdpv_extraction_method', 'none' );
		} else {
			update_post_meta( $attachment_id, '_mdpv_preview_path', $preview_result['path'] );
			update_post_meta( $attachment_id, '_mdpv_preview_generated', gmdate( 'c' ) );
			update_post_meta( $attachment_id, '_mdpv_extraction_method', $preview_result['method'] );
		}

		// Mark as processed (even if preview failed - metadata still useful)
		update_post_meta( $attachment_id, '_mdpv_processed', true );
		delete_post_meta( $attachment_id, '_mdpv_extraction_error' );

		/**
		 * Fires after PDF processing completes
		 *
		 * @since 1.0.0
		 * @param int   $attachment_id The attachment ID.
		 * @param array $metadata      Extracted metadata.
		 * @param array $preview_result Preview generation result.
		 */
		do_action( 'mdpv_pdf_processed', $attachment_id, $metadata, $preview_result );

		return true;
	}

	/**
	 * Get server capabilities
	 *
	 * Proxy method to Compatibility class for REST API access.
	 *
	 * @return array{
	 *     imagemagick: bool,
	 *     imagemagick_pdf: bool,
	 *     ghostscript: bool,
	 *     pdfinfo: bool,
	 *     gd: bool,
	 *     gd_webp: bool,
	 *     cwebp: bool,
	 *     exec_enabled: bool,
	 *     extraction_available: bool,
	 *     recommended_method: string
	 * } Capabilities array.
	 */
	public function get_capabilities(): array {
		return $this->compatibility->get_capabilities();
	}

	/**
	 * Bulk process unprocessed PDFs
	 *
	 * Processes multiple PDF attachments that haven't been processed yet.
	 * Useful for initial setup or reprocessing after configuration changes.
	 *
	 * @param int $limit Maximum number of PDFs to process in this batch.
	 * @return array{
	 *     processed: int,
	 *     failed: int,
	 *     remaining: int
	 * } Processing statistics.
	 */
	public function bulk_process( int $limit = 50 ): array {
		global $wpdb;

		// Get unprocessed PDF attachments
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_mdpv_processed'
				WHERE p.post_type = 'attachment'
				AND p.post_mime_type = 'application/pdf'
				AND pm.meta_value IS NULL
				LIMIT %d",
				$limit
			)
		);

		$processed = 0;
		$failed    = 0;

		foreach ( $attachment_ids as $attachment_id ) {
			$result = $this->process_pdf( (int) $attachment_id );
			if ( $result ) {
				++$processed;
			} else {
				++$failed;
			}
		}

		// Get remaining count
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$remaining = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_mdpv_processed'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type = 'application/pdf'
			AND pm.meta_value IS NULL"
		);

		return [
			'processed' => $processed,
			'failed'    => $failed,
			'remaining' => $remaining,
		];
	}

	/**
	 * Extract PDF metadata
	 *
	 * Tries multiple extraction methods in order of preference:
	 * 1. ImageMagick (most reliable for dimensions)
	 * 2. pdfinfo CLI tool (best for metadata)
	 * 3. Basic PHP parsing (fallback)
	 *
	 * @param string $pdf_path Full path to PDF file.
	 * @return array{page_count: int, width: int, height: int, title: string, author: string}|null
	 */
	private function extract_metadata( string $pdf_path ): ?array {
		$capabilities = $this->compatibility->get_capabilities();

		// Try ImageMagick first (best for dimensions)
		if ( $capabilities['imagemagick_pdf'] ) {
			$metadata = $this->extract_metadata_imagemagick( $pdf_path );
			if ( $metadata ) {
				// ImageMagick doesn't get title/author well, try pdfinfo for those
				if ( $capabilities['pdfinfo'] && ( empty( $metadata['title'] ) || empty( $metadata['author'] ) ) ) {
					$pdfinfo_data = $this->extract_metadata_pdfinfo( $pdf_path );
					if ( $pdfinfo_data ) {
						$metadata['title']  = $pdfinfo_data['title'] ?: $metadata['title'];
						$metadata['author'] = $pdfinfo_data['author'] ?: $metadata['author'];
					}
				}
				return $metadata;
			}
		}

		// Fall back to pdfinfo
		if ( $capabilities['pdfinfo'] ) {
			$metadata = $this->extract_metadata_pdfinfo( $pdf_path );
			if ( $metadata ) {
				return $metadata;
			}
		}

		// Last resort: basic PHP parsing
		return $this->extract_metadata_basic( $pdf_path );
	}

	/**
	 * Extract metadata using ImageMagick
	 *
	 * @param string $pdf_path Path to PDF file.
	 * @return array{page_count: int, width: int, height: int, title: string, author: string}|null
	 */
	private function extract_metadata_imagemagick( string $pdf_path ): ?array {
		try {
			$imagick = new \Imagick();

			// pingImage is faster - only reads metadata, not full image
			$imagick->pingImage( $pdf_path );

			$page_count = $imagick->getNumberImages();

			// Read first page to get dimensions
			$imagick->clear();
			$imagick->readImage( $pdf_path . '[0]' );

			$geometry = $imagick->getImageGeometry();
			$width    = (int) $geometry['width'];
			$height   = (int) $geometry['height'];

			$imagick->clear();
			$imagick->destroy();

			return [
				'page_count' => $page_count,
				'width'      => $width,
				'height'     => $height,
				'title'      => '',
				'author'     => '',
			];
		} catch ( \ImagickException $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'MDPV ImageMagick metadata error: ' . $e->getMessage() );
			}
			return null;
		}
	}

	/**
	 * Extract metadata using pdfinfo CLI tool
	 *
	 * @param string $pdf_path Path to PDF file.
	 * @return array{page_count: int, width: int, height: int, title: string, author: string}|null
	 */
	private function extract_metadata_pdfinfo( string $pdf_path ): ?array {
		if ( ! $this->compatibility->get_capabilities()['exec_enabled'] ) {
			return null;
		}

		$output     = [];
		$return_var = 0;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec(
			sprintf( 'pdfinfo %s 2>/dev/null', escapeshellarg( $pdf_path ) ),
			$output,
			$return_var
		);

		if ( 0 !== $return_var || empty( $output ) ) {
			return null;
		}

		$metadata = [
			'page_count' => 1,
			'width'      => 612,  // Default US Letter width in points
			'height'     => 792,  // Default US Letter height in points
			'title'      => '',
			'author'     => '',
		];

		foreach ( $output as $line ) {
			// Page count: "Pages:          47"
			if ( preg_match( '/^Pages:\s+(\d+)/i', $line, $matches ) ) {
				$metadata['page_count'] = (int) $matches[1];
			}
			// Page size: "Page size:      612 x 792 pts (letter)"
			elseif ( preg_match( '/^Page size:\s+([\d.]+)\s*x\s*([\d.]+)/i', $line, $matches ) ) {
				$metadata['width']  = (int) round( (float) $matches[1] );
				$metadata['height'] = (int) round( (float) $matches[2] );
			}
			// Title: "Title:          My Document"
			elseif ( preg_match( '/^Title:\s+(.+)$/i', $line, $matches ) ) {
				$metadata['title'] = sanitize_text_field( trim( $matches[1] ) );
			}
			// Author: "Author:         John Doe"
			elseif ( preg_match( '/^Author:\s+(.+)$/i', $line, $matches ) ) {
				$metadata['author'] = sanitize_text_field( trim( $matches[1] ) );
			}
		}

		// Validate we got meaningful data
		if ( $metadata['page_count'] < 1 ) {
			return null;
		}

		return $metadata;
	}

	/**
	 * Extract basic metadata by parsing PDF structure
	 *
	 * This is a fallback method when no external tools are available.
	 * It's less reliable but works for most standard PDFs.
	 *
	 * @param string $pdf_path Path to PDF file.
	 * @return array{page_count: int, width: int, height: int, title: string, author: string}
	 */
	private function extract_metadata_basic( string $pdf_path ): array {
		// Read first 100KB of PDF (metadata is in header)
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $pdf_path, false, null, 0, 102400 );

		if ( false === $content ) {
			return $this->get_default_metadata();
		}

		$metadata = $this->get_default_metadata();

		// Count page objects: /Type /Page (but not /Pages)
		// This regex looks for /Type followed by whitespace and /Page not followed by 's'
		if ( preg_match_all( '/\/Type\s*\/Page[^s]/i', $content, $matches ) ) {
			$metadata['page_count'] = max( 1, count( $matches[0] ) );
		}

		// Try to extract MediaBox for dimensions: /MediaBox [0 0 612 792]
		if ( preg_match( '/\/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)\s*\]/i', $content, $matches ) ) {
			$metadata['width']  = (int) round( (float) $matches[1] );
			$metadata['height'] = (int) round( (float) $matches[2] );
		}

		// Try to extract title: /Title (My Document) or /Title <hex>
		if ( preg_match( '/\/Title\s*\(([^)]+)\)/i', $content, $matches ) ) {
			$metadata['title'] = sanitize_text_field( $this->decode_pdf_string( $matches[1] ) );
		}

		// Try to extract author: /Author (John Doe)
		if ( preg_match( '/\/Author\s*\(([^)]+)\)/i', $content, $matches ) ) {
			$metadata['author'] = sanitize_text_field( $this->decode_pdf_string( $matches[1] ) );
		}

		return $metadata;
	}

	/**
	 * Get default metadata values
	 *
	 * @return array{page_count: int, width: int, height: int, title: string, author: string}
	 */
	private function get_default_metadata(): array {
		return [
			'page_count' => 1,
			'width'      => 612,   // US Letter width in points
			'height'     => 792,   // US Letter height in points
			'title'      => '',
			'author'     => '',
		];
	}

	/**
	 * Decode PDF string escapes
	 *
	 * @param string $str Raw PDF string.
	 * @return string Decoded string.
	 */
	private function decode_pdf_string( string $str ): string {
		// Handle common PDF escape sequences
		$replacements = [
			'\\n'  => "\n",
			'\\r'  => "\r",
			'\\t'  => "\t",
			'\\\\' => '\\',
			'\\('  => '(',
			'\\)'  => ')',
		];

		return str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$str
		);
	}

	/**
	 * Generate preview image for PDF
	 *
	 * Tries ImageMagick first, then Ghostscript fallback.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $pdf_path      Full path to PDF file.
	 * @return array{success: bool, path?: string, method?: string, error?: string}
	 */
	private function generate_preview( int $attachment_id, string $pdf_path ): array {
		// Ensure cache directory exists
		if ( ! $this->cache->create_cache_directory() ) {
			return [
				'success' => false,
				'error'   => __( 'Failed to create cache directory', 'maxtdesign-pdf-viewer' ),
			];
		}

		$cache_dir   = $this->cache->get_cache_directory();
		$output_file = $attachment_id . '-p1.webp';
		$output_path = $cache_dir . $output_file;

		// Get quality settings
		$quality_preset = $this->settings->get( 'preview_quality', 'medium' );
		$preset         = self::QUALITY_PRESETS[ $quality_preset ] ?? self::QUALITY_PRESETS['medium'];

		$capabilities = $this->compatibility->get_capabilities();

		// Try ImageMagick first (with memory safety)
		if ( $capabilities['imagemagick_pdf'] ) {
			$result = $this->generate_preview_imagemagick_safe(
				$pdf_path,
				$output_path,
				$preset['resolution'],
				$preset['quality']
			);

			if ( $result ) {
				return [
					'success' => true,
					'path'    => 'mdpv-cache/' . $output_file,
					'method'  => 'imagemagick',
				];
			}
		}

		// Try Ghostscript fallback
		if ( $capabilities['ghostscript'] ) {
			$result = $this->generate_preview_ghostscript(
				$pdf_path,
				$output_path,
				$preset['resolution'],
				$preset['quality']
			);

			if ( $result ) {
				return [
					'success' => true,
					'path'    => 'mdpv-cache/' . $output_file,
					'method'  => 'ghostscript',
				];
			}

			// If we have Ghostscript but no WebP support, try JPEG fallback
			if ( ! $capabilities['gd_webp'] && ! $capabilities['cwebp'] ) {
				$jpeg_file = $attachment_id . '-p1.jpg';
				$jpeg_path = $cache_dir . $jpeg_file;

				$result = $this->generate_preview_ghostscript_jpeg(
					$pdf_path,
					$jpeg_path,
					$preset['resolution'],
					$preset['quality']
				);

				if ( $result ) {
					return [
						'success' => true,
						'path'    => 'mdpv-cache/' . $jpeg_file,
						'method'  => 'ghostscript-jpeg',
					];
				}
			}
		}

		// No extraction method available
		return [
			'success' => false,
			'error'   => __( 'No preview extraction method available. Install ImageMagick or Ghostscript.', 'maxtdesign-pdf-viewer' ),
		];
	}

	/**
	 * Generate preview using ImageMagick PHP extension
	 *
	 * @param string $pdf_path    Full path to PDF file.
	 * @param string $output_path Full path for output WebP file.
	 * @param int    $resolution  Resolution in DPI (72, 150, or 300).
	 * @param int    $quality     WebP quality (0-100).
	 * @return bool True on success.
	 */
	private function generate_preview_imagemagick(
		string $pdf_path,
		string $output_path,
		int $resolution,
		int $quality
	): bool {
		try {
			$imagick = new \Imagick();

			// Set resolution BEFORE reading (important for quality)
			$imagick->setResolution( $resolution, $resolution );

			// Read only first page: filename[0]
			$imagick->readImage( $pdf_path . '[0]' );

			// Flatten transparency to white background
			// This is important - PDFs often have transparent backgrounds
			$imagick->setImageBackgroundColor( 'white' );
			$imagick->setImageAlphaChannel( \Imagick::ALPHACHANNEL_REMOVE );

			// Merge layers (handles PDFs with multiple layers)
			$imagick = $imagick->mergeImageLayers( \Imagick::LAYERMETHOD_FLATTEN );

			// Convert to WebP
			$imagick->setImageFormat( 'webp' );
			$imagick->setImageCompressionQuality( $quality );

			// Strip metadata to reduce file size
			$imagick->stripImage();

			// Write the file
			$success = $imagick->writeImage( $output_path );

			// Clean up
			$imagick->clear();
			$imagick->destroy();

			// Verify file was created
			if ( $success && file_exists( $output_path ) && filesize( $output_path ) > 0 ) {
				/**
				 * Fires after preview is generated
				 *
				 * @since 1.0.0
				 * @param string $output_path Path to generated preview.
				 * @param string $pdf_path    Path to source PDF.
				 * @param string $method      Extraction method used.
				 */
				do_action( 'mdpv_preview_generated', $output_path, $pdf_path, 'imagemagick' );

				return true;
			}

			return false;

		} catch ( \ImagickException $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'MDPV ImageMagick preview error: ' . $e->getMessage() );
			}

			// Clean up on failure
			if ( file_exists( $output_path ) ) {
				wp_delete_file( $output_path );
			}

			return false;
		}
	}

	/**
	 * Generate preview with memory limit handling
	 *
	 * For very large PDFs, we may need to adjust memory limits.
	 *
	 * @param string $pdf_path    Full path to PDF file.
	 * @param string $output_path Full path for output WebP file.
	 * @param int    $resolution  Resolution in DPI.
	 * @param int    $quality     WebP quality.
	 * @return bool True on success.
	 */
	private function generate_preview_imagemagick_safe(
		string $pdf_path,
		string $output_path,
		int $resolution,
		int $quality
	): bool {
		// Check PDF file size
		$file_size = filesize( $pdf_path );

		// For PDFs over 10MB, use lower resolution to prevent memory issues
		if ( $file_size > 10 * 1024 * 1024 ) {
			$resolution = min( $resolution, 100 );
		}

		// For PDFs over 50MB, use minimum resolution
		if ( $file_size > 50 * 1024 * 1024 ) {
			$resolution = 72;
		}

		return $this->generate_preview_imagemagick( $pdf_path, $output_path, $resolution, $quality );
	}

	/**
	 * Generate preview using Ghostscript CLI
	 *
	 * Ghostscript renders to PNG, then we convert to WebP.
	 * This is the fallback when ImageMagick is unavailable.
	 *
	 * @param string $pdf_path    Full path to PDF file.
	 * @param string $output_path Full path for output WebP file.
	 * @param int    $resolution  Resolution in DPI.
	 * @param int    $quality     WebP quality (0-100).
	 * @return bool True on success.
	 */
	private function generate_preview_ghostscript(
		string $pdf_path,
		string $output_path,
		int $resolution,
		int $quality
	): bool {
		$capabilities = $this->compatibility->get_capabilities();

		if ( ! $capabilities['exec_enabled'] || ! $capabilities['ghostscript'] ) {
			return false;
		}

		// Create unique temp file for PNG intermediate
		$temp_png = $this->get_temp_file_path( 'png' );

		if ( ! $temp_png ) {
			return false;
		}

		try {
			// Build Ghostscript command
			// -dNOPAUSE: Don't pause between pages
			// -dBATCH: Exit after processing
			// -dSAFER: Security flag (restricts file operations)
			// -sDEVICE=png16m: 24-bit RGB PNG output
			// -r{resolution}: Resolution in DPI
			// -dFirstPage=1 -dLastPage=1: Only first page
			// -dTextAlphaBits=4 -dGraphicsAlphaBits=4: Anti-aliasing
			// -sOutputFile: Output file path
			$gs_command = sprintf(
				'gs -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r%d -dFirstPage=1 -dLastPage=1 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=%s %s 2>/dev/null',
				$resolution,
				escapeshellarg( $temp_png ),
				escapeshellarg( $pdf_path )
			);

			$output     = [];
			$return_var = 0;

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			exec( $gs_command, $output, $return_var );

			// Check if PNG was created
			if ( 0 !== $return_var || ! file_exists( $temp_png ) || filesize( $temp_png ) === 0 ) {
				$this->cleanup_temp_file( $temp_png );
				return false;
			}

			// Convert PNG to WebP
			$webp_success = $this->convert_png_to_webp( $temp_png, $output_path, $quality );

			// Clean up temp PNG
			$this->cleanup_temp_file( $temp_png );

			if ( $webp_success && file_exists( $output_path ) && filesize( $output_path ) > 0 ) {
				/**
				 * Fires after preview is generated
				 *
				 * @since 1.0.0
				 * @param string $output_path Path to generated preview.
				 * @param string $pdf_path    Path to source PDF.
				 * @param string $method      Extraction method used.
				 */
				do_action( 'mdpv_preview_generated', $output_path, $pdf_path, 'ghostscript' );

				return true;
			}

			return false;

		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'MDPV Ghostscript preview error: ' . $e->getMessage() );
			}

			// Clean up on failure
			$this->cleanup_temp_file( $temp_png );
			if ( file_exists( $output_path ) ) {
				wp_delete_file( $output_path );
			}

			return false;
		}
	}

	/**
	 * Convert PNG to WebP
	 *
	 * Tries GD first (no external dependency), then cwebp CLI.
	 *
	 * @param string $png_path  Path to source PNG.
	 * @param string $webp_path Path for output WebP.
	 * @param int    $quality   WebP quality (0-100).
	 * @return bool True on success.
	 */
	private function convert_png_to_webp( string $png_path, string $webp_path, int $quality ): bool {
		$capabilities = $this->compatibility->get_capabilities();

		// Try GD first (most common)
		if ( $capabilities['gd_webp'] ) {
			$image = imagecreatefrompng( $png_path );

			if ( $image ) {
				// Flatten transparency to white background (consistent with ImageMagick)
				$width = imagesx( $image );
				$height = imagesy( $image );
				$bg     = imagecreatetruecolor( $width, $height );
				$white  = imagecolorallocate( $bg, 255, 255, 255 );

				imagefill( $bg, 0, 0, $white );
				imagealphablending( $bg, true );
				imagecopy( $bg, $image, 0, 0, 0, 0, $width, $height );

				$success = imagewebp( $bg, $webp_path, $quality );

				// Clean up image resources (PHP 8.0+ compatible)
				unset( $image, $bg );

				if ( $success ) {
					return true;
				}
			}
		}

		// Fall back to cwebp CLI
		if ( $capabilities['exec_enabled'] && $capabilities['cwebp'] ) {
			$cwebp_command = sprintf(
				'cwebp -q %d %s -o %s 2>/dev/null',
				$quality,
				escapeshellarg( $png_path ),
				escapeshellarg( $webp_path )
			);

			$output     = [];
			$return_var = 0;

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			exec( $cwebp_command, $output, $return_var );

			return 0 === $return_var && file_exists( $webp_path );
		}

		// Last resort: keep as PNG (not ideal but functional)
		// Actually, let's return false - we want WebP specifically
		return false;
	}

	/**
	 * Convert PNG to JPEG as fallback
	 *
	 * Only used if WebP conversion fails entirely.
	 *
	 * @param string $png_path  Path to source PNG.
	 * @param string $jpeg_path Path for output JPEG.
	 * @param int    $quality   JPEG quality (0-100).
	 * @return bool True on success.
	 */
	private function convert_png_to_jpeg( string $png_path, string $jpeg_path, int $quality ): bool {
		if ( ! function_exists( 'imagecreatefrompng' ) ) {
			return false;
		}

		$image = imagecreatefrompng( $png_path );

		if ( ! $image ) {
			return false;
		}

		// Create white background for transparency
		$width  = imagesx( $image );
		$height = imagesy( $image );
		$bg     = imagecreatetruecolor( $width, $height );
		$white  = imagecolorallocate( $bg, 255, 255, 255 );

		imagefill( $bg, 0, 0, $white );
		imagecopy( $bg, $image, 0, 0, 0, 0, $width, $height );

		$success = imagejpeg( $bg, $jpeg_path, $quality );

		// Clean up image resources (PHP 8.0+ compatible)
		unset( $image, $bg );

		return $success;
	}

	/**
	 * Get a unique temporary file path
	 *
	 * @param string $extension File extension (without dot).
	 * @return string|null Temp file path or null on failure.
	 */
	private function get_temp_file_path( string $extension ): ?string {
		$temp_dir = sys_get_temp_dir();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- System temp directory check, WP_Filesystem not applicable
		if ( ! is_writable( $temp_dir ) ) {
			// Try WordPress temp directory
			$temp_dir = get_temp_dir();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- System temp directory check, WP_Filesystem not applicable
		if ( ! is_writable( $temp_dir ) ) {
			return null;
		}

		// Generate unique filename
		$filename = 'mdpv_' . wp_generate_uuid4() . '.' . $extension;

		return trailingslashit( $temp_dir ) . $filename;
	}

	/**
	 * Clean up temporary file
	 *
	 * @param string $file_path Path to temp file.
	 * @return void
	 */
	private function cleanup_temp_file( string $file_path ): void {
		if ( file_exists( $file_path ) ) {
			wp_delete_file( $file_path );
		}
	}

	/**
	 * Generate preview using Ghostscript with JPEG output
	 *
	 * Fallback when WebP conversion is not available.
	 *
	 * @param string $pdf_path    Full path to PDF file.
	 * @param string $output_path Full path for output JPEG file.
	 * @param int    $resolution  Resolution in DPI.
	 * @param int    $quality     JPEG quality (0-100).
	 * @return bool True on success.
	 */
	private function generate_preview_ghostscript_jpeg(
		string $pdf_path,
		string $output_path,
		int $resolution,
		int $quality
	): bool {
		$capabilities = $this->compatibility->get_capabilities();

		if ( ! $capabilities['exec_enabled'] || ! $capabilities['ghostscript'] ) {
			return false;
		}

		// Create unique temp file for PNG intermediate
		$temp_png = $this->get_temp_file_path( 'png' );

		if ( ! $temp_png ) {
			return false;
		}

		try {
			// Build Ghostscript command
			$gs_command = sprintf(
				'gs -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r%d -dFirstPage=1 -dLastPage=1 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=%s %s 2>/dev/null',
				$resolution,
				escapeshellarg( $temp_png ),
				escapeshellarg( $pdf_path )
			);

			$output     = [];
			$return_var = 0;

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			exec( $gs_command, $output, $return_var );

			// Check if PNG was created
			if ( 0 !== $return_var || ! file_exists( $temp_png ) || filesize( $temp_png ) === 0 ) {
				$this->cleanup_temp_file( $temp_png );
				return false;
			}

			// Convert PNG to JPEG
			$jpeg_success = $this->convert_png_to_jpeg( $temp_png, $output_path, $quality );

			// Clean up temp PNG
			$this->cleanup_temp_file( $temp_png );

			if ( $jpeg_success && file_exists( $output_path ) && filesize( $output_path ) > 0 ) {
				/**
				 * Fires after preview is generated
				 *
				 * @since 1.0.0
				 * @param string $output_path Path to generated preview.
				 * @param string $pdf_path    Path to source PDF.
				 * @param string $method      Extraction method used.
				 */
				do_action( 'mdpv_preview_generated', $output_path, $pdf_path, 'ghostscript-jpeg' );

				return true;
			}

			return false;

		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'MDPV Ghostscript JPEG preview error: ' . $e->getMessage() );
			}

			// Clean up on failure
			$this->cleanup_temp_file( $temp_png );
			if ( file_exists( $output_path ) ) {
				wp_delete_file( $output_path );
			}

			return false;
		}
	}

	/**
	 * Log error for attachment
	 *
	 * Saves error message to post meta and optionally logs to error_log
	 * if WP_DEBUG is enabled.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $error         Error message to log.
	 * @return void
	 */
	private function log_error( int $attachment_id, string $error ): void {
		update_post_meta( $attachment_id, '_mdpv_extraction_error', $error );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only used in debug mode
			error_log(
				sprintf(
					'MaxtDesign PDF Viewer: Extraction error for attachment %d: %s',
					$attachment_id,
					$error
				)
			);
		}
	}
}

