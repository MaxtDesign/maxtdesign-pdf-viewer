<?php
/**
 * Cache Manager Class
 *
 * Manages preview image cache directory, file storage, cleanup, and statistics.
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
 * Cache Manager Class
 *
 * Handles cache directory creation, preview file management, URL generation,
 * cleanup operations, and cache statistics.
 */
class Cache {

	/**
	 * Cache directory name
	 *
	 * @var string
	 */
	private const CACHE_DIR = 'mdpv-cache';

	/**
	 * Settings service instance
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor
	 *
	 * @param Settings $settings Settings service instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get full path to cache directory
	 *
	 * @return string Cache directory path with trailing slash.
	 */
	public function get_cache_directory(): string {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . self::CACHE_DIR . '/';
	}

	/**
	 * Get full URL to cache directory
	 *
	 * @return string Cache directory URL with trailing slash.
	 */
	public function get_cache_url(): string {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['baseurl'] ) . self::CACHE_DIR . '/';
	}

	/**
	 * Create cache directory with security files
	 *
	 * Creates the cache directory if it doesn't exist and adds
	 * index.php and .htaccess files for security.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function create_cache_directory(): bool {
		$cache_dir = $this->get_cache_directory();

		// Create directory if it doesn't exist
		if ( ! wp_mkdir_p( $cache_dir ) ) {
			return false;
		}

		// Create index.php to prevent directory listing
		$index_file = $cache_dir . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			file_put_contents( $index_file, "<?php // Silence is golden.\n" );
		}

		// Create .htaccess with security rules
		$htaccess_file = $cache_dir . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# MaxtDesign PDF Viewer Cache\n";
			$htaccess_content .= "Options -Indexes\n\n";
			$htaccess_content .= "# Prevent PHP execution\n";
			$htaccess_content .= "<FilesMatch \"\\.php$\">\n";
			$htaccess_content .= "    Deny from all\n";
			$htaccess_content .= "</FilesMatch>\n\n";
			$htaccess_content .= "# Only allow image files\n";
			$htaccess_content .= "<FilesMatch \"\\.(webp|jpg|jpeg|png)$\">\n";
			$htaccess_content .= "    Allow from all\n";
			$htaccess_content .= "</FilesMatch>\n";

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			file_put_contents( $htaccess_file, $htaccess_content );
		}

		return true;
	}

	/**
	 * Get preview URL for specific attachment
	 *
	 * Retrieves the preview image URL for a PDF attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string|null Preview URL or null if no preview exists.
	 */
	public function get_preview_url( int $attachment_id ): ?string {
		$preview_path = get_post_meta( $attachment_id, '_mdpv_preview_path', true );

		if ( empty( $preview_path ) ) {
			return null;
		}

		// Handle both 'mdpv-cache/filename.webp' and 'filename.webp' formats
		$filename = strpos( $preview_path, 'mdpv-cache/' ) === 0
			? substr( $preview_path, strlen( 'mdpv-cache/' ) )
			: $preview_path;

		$full_path = $this->get_cache_directory() . $filename;

		// Verify file exists before returning URL
		if ( ! file_exists( $full_path ) ) {
			return null;
		}

		$cache_url = $this->get_cache_url();
		return $cache_url . $filename;
	}

	/**
	 * Get full filesystem path to preview
	 *
	 * Retrieves the full filesystem path to the preview image file.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string|null Full preview path or null if no preview exists.
	 */
	public function get_preview_path( int $attachment_id ): ?string {
		$preview_path = get_post_meta( $attachment_id, '_mdpv_preview_path', true );

		if ( empty( $preview_path ) ) {
			return null;
		}

		// Handle both 'mdpv-cache/filename.webp' and 'filename.webp' formats
		$filename = strpos( $preview_path, 'mdpv-cache/' ) === 0
			? substr( $preview_path, strlen( 'mdpv-cache/' ) )
			: $preview_path;

		$full_path = $this->get_cache_directory() . $filename;

		// Verify file exists before returning path
		if ( ! file_exists( $full_path ) ) {
			return null;
		}

		return $full_path;
	}

	/**
	 * Delete preview file for attachment
	 *
	 * Removes the preview file and clears all related post meta.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_preview( int $attachment_id ): bool {
		$preview_path = $this->get_preview_path( $attachment_id );

		if ( null !== $preview_path ) {
			wp_delete_file( $preview_path );
		}

		// Clear all related post meta
		$meta_keys = [
			'_mdpv_processed',
			'_mdpv_preview_path',
			'_mdpv_preview_generated',
			'_mdpv_extraction_method',
			'_mdpv_extraction_error',
		];

		foreach ( $meta_keys as $meta_key ) {
			delete_post_meta( $attachment_id, $meta_key );
		}

		return true;
	}

	/**
	 * Get cache statistics
	 *
	 * Returns array with total files, total size, and oldest file timestamp.
	 *
	 * @return array{
	 *     total_files: int,
	 *     total_size: int,
	 *     oldest_file: string|null
	 * } Cache statistics array.
	 */
	public function get_stats(): array {
		$cache_dir = $this->get_cache_directory();

		if ( ! is_dir( $cache_dir ) ) {
			return [
				'total_files' => 0,
				'total_size'  => 0,
				'oldest_file' => null,
			];
		}

		$files = glob( $cache_dir . '*.webp' );

		if ( false === $files || empty( $files ) ) {
			return [
				'total_files' => 0,
				'total_size'  => 0,
				'oldest_file' => null,
			];
		}

		$total_size  = 0;
		$oldest_time = null;
		$oldest_file = null;

		foreach ( $files as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}

			$file_size = filesize( $file );
			if ( false !== $file_size ) {
				$total_size += $file_size;
			}

			$file_time = filemtime( $file );
			if ( false !== $file_time && ( null === $oldest_time || $file_time < $oldest_time ) ) {
				$oldest_time = $file_time;
				$oldest_file = gmdate( 'c', $file_time );
			}
		}

		return [
			'total_files' => count( $files ),
			'total_size'  => $total_size,
			'oldest_file' => $oldest_file,
		];
	}

	/**
	 * Clear all preview files
	 *
	 * Deletes all preview files from cache directory and removes
	 * all related post meta from the database.
	 *
	 * @return int Number of deleted files.
	 */
	public function clear_all(): int {
		$cache_dir = $this->get_cache_directory();

		if ( ! is_dir( $cache_dir ) ) {
			return 0;
		}

		$files   = glob( $cache_dir . '*.webp' );
		$deleted = 0;

		if ( false === $files || empty( $files ) ) {
			// Still need to clear metadata even if no files
			$this->clear_all_metadata();
			return 0;
		}

		foreach ( $files as $file ) {
			if ( wp_delete_file( $file ) !== false ) {
				++$deleted;
			}
		}

		// Clear all preview metadata
		$this->clear_all_metadata();

		return $deleted;
	}

	/**
	 * Cleanup old files based on cache duration setting
	 *
	 * Deletes preview files older than the configured cache_duration
	 * and removes their related metadata.
	 *
	 * @return int Number of deleted files.
	 */
	public function cleanup_old_files(): int {
		$cache_dir = $this->get_cache_directory();

		if ( ! is_dir( $cache_dir ) ) {
			return 0;
		}

		$cache_duration = (int) $this->settings->get( 'cache_duration', 30 );
		$cutoff_time    = time() - ( $cache_duration * DAY_IN_SECONDS );

		$files   = glob( $cache_dir . '*.webp' );
		$deleted = 0;

		if ( false === $files || empty( $files ) ) {
			update_option( 'mdpv_last_cleanup', time() );
			return 0;
		}

		foreach ( $files as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}

			$file_time = filemtime( $file );

			if ( false === $file_time || $file_time < $cutoff_time ) {
				$file_deleted = false;

				// Find attachment ID from filename if possible
				// File naming pattern: {attachment_id}-p1.webp or {attachment_id}.webp
				$filename_base = basename( $file, '.webp' );
				// Extract attachment ID (handles both '123-p1' and '123' formats)
				$attachment_id = (int) str_replace( '-p1', '', $filename_base );
				if ( $attachment_id > 0 ) {
					// Get the path from meta to see if it matches current file
					$meta_path = get_post_meta( $attachment_id, '_mdpv_preview_path', true );
					if ( ! empty( $meta_path ) ) {
						// Handle both 'mdpv-cache/filename.webp' and 'filename.webp' formats
						$meta_filename = strpos( $meta_path, 'mdpv-cache/' ) === 0
							? substr( $meta_path, strlen( 'mdpv-cache/' ) )
							: $meta_path;
						$meta_full_path = $this->get_cache_directory() . $meta_filename;
						// If meta points to this file, use delete_preview for complete cleanup
						if ( $meta_full_path === $file ) {
							$this->delete_preview( $attachment_id );
							$file_deleted = true;
						}
					}
				}

				// Delete the file if it still exists (handles orphaned files)
				if ( ! $file_deleted && file_exists( $file ) ) {
					$file_deleted = ( wp_delete_file( $file ) !== false );
				}

				if ( $file_deleted ) {
					++$deleted;
				}
			}
		}

		update_option( 'mdpv_last_cleanup', time() );

		return $deleted;
	}

	/**
	 * Clear all preview metadata from database
	 *
	 * Removes all preview-related post meta using direct database query
	 * for better performance when clearing large amounts of data.
	 *
	 * @return void
	 */
	private function clear_all_metadata(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"DELETE FROM {$wpdb->postmeta} 
			WHERE meta_key IN (
				'_mdpv_processed',
				'_mdpv_preview_path',
				'_mdpv_preview_generated',
				'_mdpv_extraction_method',
				'_mdpv_extraction_error'
			)"
		);
	}
}

