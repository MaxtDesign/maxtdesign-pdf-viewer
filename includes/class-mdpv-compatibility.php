<?php
/**
 * Server Compatibility Checker Class
 *
 * Detects server capabilities for PDF processing (ImageMagick PHP extension, GD library)
 * and provides diagnostic information for administrators.
 * Note: CLI tools (Ghostscript, pdfinfo, cwebp) are no longer supported for security reasons.
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
 * Compatibility Checker Class
 *
 * Checks for server-side PDF processing tools and provides capability reports.
 */
class Compatibility {

	/**
	 * Capability cache transient name
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'mdpv_server_capabilities';

	/**
	 * Cache duration in seconds (1 hour)
	 *
	 * @var int
	 */
	private const CACHE_DURATION = HOUR_IN_SECONDS;

	/**
	 * Get server capabilities with caching
	 *
	 * Returns cached capabilities or runs fresh checks if cache expired or forced.
	 *
	 * @param bool $force_refresh Whether to bypass cache and run fresh checks.
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
	public function get_capabilities( bool $force_refresh = false ): array {
		// Return cached results if not forcing refresh
		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		// Run fresh capability checks
		$capabilities = [
			'imagemagick'         => $this->has_imagemagick(),
			'imagemagick_pdf'     => $this->has_imagemagick(),
			'ghostscript'         => $this->has_ghostscript(),
			'pdfinfo'             => $this->has_pdfinfo(),
			'gd'                  => $this->has_gd(),
			'gd_webp'             => $this->has_gd_webp(),
			'cwebp'               => $this->has_cwebp(),
			'exec_enabled'        => $this->is_exec_enabled(),
			'extraction_available' => false,
			'recommended_method'   => 'none',
		];

		// Determine if extraction is available
		// Note: Only ImageMagick PHP extension is supported (no CLI tools for security)
		$capabilities['extraction_available'] = $capabilities['imagemagick'];

		// Determine recommended method
		$capabilities['recommended_method'] = $this->get_recommended_method( $capabilities );

		// Cache results
		set_transient( self::CACHE_KEY, $capabilities, self::CACHE_DURATION );

		return $capabilities;
	}

	/**
	 * Check if ImageMagick PHP extension is available with PDF support
	 *
	 * @return bool True if Imagick class exists and PDF format is supported.
	 */
	public function has_imagemagick(): bool {
		if ( ! class_exists( 'Imagick' ) ) {
			return false;
		}

		try {
			$formats = \Imagick::queryFormats();
			return in_array( 'PDF', $formats, true );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Check if Ghostscript is available via command line
	 *
	 * Note: This method always returns false as we no longer use exec() for security reasons.
	 * Ghostscript CLI is not supported. Use ImageMagick PHP extension instead.
	 *
	 * @return bool Always returns false.
	 */
	public function has_ghostscript(): bool {
		return false;
	}

	/**
	 * Check if pdfinfo command is available
	 *
	 * Note: This method always returns false as we no longer use exec() for security reasons.
	 * pdfinfo CLI is not supported. Use ImageMagick PHP extension or basic parsing instead.
	 *
	 * @return bool Always returns false.
	 */
	public function has_pdfinfo(): bool {
		return false;
	}

	/**
	 * Check if cwebp command is available
	 *
	 * Note: This method always returns false as we no longer use exec() for security reasons.
	 * cwebp CLI is not supported. Use GD library with WebP support instead.
	 *
	 * @return bool Always returns false.
	 */
	public function has_cwebp(): bool {
		return false;
	}

	/**
	 * Check if GD extension is loaded
	 *
	 * @return bool True if GD extension is available.
	 */
	public function has_gd(): bool {
		return extension_loaded( 'gd' );
	}

	/**
	 * Check if GD extension supports WebP
	 *
	 * @return bool True if GD is loaded and imagewebp() function exists.
	 */
	public function has_gd_webp(): bool {
		if ( ! $this->has_gd() ) {
			return false;
		}

		return function_exists( 'imagewebp' );
	}

	/**
	 * Check if PHP exec() function is enabled
	 *
	 * Note: This method always returns false as we no longer use exec() for security reasons.
	 * All functionality now uses PHP extensions (ImageMagick, GD) instead of CLI tools.
	 *
	 * @return bool Always returns false.
	 */
	public function is_exec_enabled(): bool {
		return false;
	}

	/**
	 * Get recommended extraction method based on available capabilities
	 *
	 * Priority: ImageMagick > none
	 * Note: Ghostscript CLI is no longer supported for security reasons.
	 *
	 * @param array<string, mixed>|null $capabilities Optional capabilities array. If not provided, will fetch fresh.
	 * @return string Recommended method: 'imagemagick' or 'none'.
	 */
	public function get_recommended_method( ?array $capabilities = null ): string {
		if ( null === $capabilities ) {
			$capabilities = $this->get_capabilities();
		}

		if ( ! empty( $capabilities['imagemagick'] ) && $capabilities['imagemagick'] ) {
			return 'imagemagick';
		}

		return 'none';
	}

	/**
	 * Get human-readable diagnostic report for admin
	 *
	 * @return array{
	 *     status: string,
	 *     message: string,
	 *     checks: array<int, array{name: string, status: bool, message: string}>
	 * } Diagnostic report array.
	 */
	public function get_diagnostic_report(): array {
		$capabilities = $this->get_capabilities();

		$checks = [
			[
				'name'    => __( 'ImageMagick', 'maxtdesign-pdf-viewer' ),
				'status'  => $capabilities['imagemagick'],
				'message' => $capabilities['imagemagick']
					? __( 'Available with PDF support', 'maxtdesign-pdf-viewer' )
					: __( 'Not available', 'maxtdesign-pdf-viewer' ),
			],
			[
				'name'    => __( 'WebP Support', 'maxtdesign-pdf-viewer' ),
				'status'  => $capabilities['gd_webp'],
				'message' => $this->get_webp_status_message( $capabilities ),
			],
		];

		// Determine overall status
		$status  = $this->determine_overall_status( $capabilities );
		$message = $this->get_status_message( $status, $capabilities );

		return [
			'status'  => $status,
			'message' => $message,
			'checks'  => $checks,
		];
	}

	/**
	 * Clear capability cache
	 *
	 * Forces fresh capability checks on next call to get_capabilities().
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Get WebP support status message
	 *
	 * @param array<string, mixed> $capabilities Capabilities array.
	 * @return string Status message.
	 */
	private function get_webp_status_message( array $capabilities ): string {
		if ( $capabilities['gd_webp'] ) {
			return __( 'GD library with WebP support', 'maxtdesign-pdf-viewer' );
		}

		return __( 'Not available', 'maxtdesign-pdf-viewer' );
	}

	/**
	 * Determine overall compatibility status
	 *
	 * @param array<string, mixed> $capabilities Capabilities array.
	 * @return string Status: 'good', 'limited', or 'unavailable'.
	 */
	private function determine_overall_status( array $capabilities ): string {
		if ( $capabilities['extraction_available'] ) {
			// Good: Has extraction method and WebP support
			if ( $capabilities['gd_webp'] ) {
				return 'good';
			}
			// Limited: Has extraction but no WebP
			return 'limited';
		}

		return 'unavailable';
	}

	/**
	 * Get status message based on overall status
	 *
	 * @param string               $status       Overall status.
	 * @param array<string, mixed> $capabilities Capabilities array.
	 * @return string Status message.
	 */
	private function get_status_message( string $status, array $capabilities ): string {
		switch ( $status ) {
			case 'good':
				return __( 'Server fully configured for PDF preview extraction.', 'maxtdesign-pdf-viewer' );

			case 'limited':
				return __( 'PDF extraction available via ImageMagick, but WebP conversion is not available. Preview images will use JPEG format.', 'maxtdesign-pdf-viewer' );

			case 'unavailable':
			default:
				return __( 'No PDF extraction methods available. Please install the ImageMagick PHP extension with PDF support.', 'maxtdesign-pdf-viewer' );
		}
	}
}

