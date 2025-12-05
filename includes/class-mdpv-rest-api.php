<?php
/**
 * REST API Endpoints
 *
 * Provides REST API endpoints for PDF viewer functionality.
 *
 * @package MaxtDesign\PDFViewer
 * @since 1.0.0
 */

declare(strict_types=1);

namespace MaxtDesign\PDFViewer;

// Security check - exit if accessed directly
defined( 'ABSPATH' ) || exit;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API class
 */
final class REST_API {

	/**
	 * REST namespace
	 */
	public const NAMESPACE = 'mdpv/v1';

	/**
	 * Plugin instance
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register REST routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Get PDF info
		register_rest_route(
			self::NAMESPACE,
			'/pdf/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_pdf_info' ],
				'permission_callback' => [ $this, 'get_pdf_permissions' ],
				'args'                => [
					'id' => [
						'description'       => __( 'PDF attachment ID.', 'maxtdesign-pdf-viewer' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					],
				],
				'schema'              => [ $this, 'get_pdf_schema' ],
			]
		);

		// Process PDF (generate preview)
		register_rest_route(
			self::NAMESPACE,
			'/pdf/(?P<id>\d+)/process',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'process_pdf' ],
				'permission_callback' => [ $this, 'process_pdf_permissions' ],
				'args'                => [
					'id' => [
						'description'       => __( 'PDF attachment ID.', 'maxtdesign-pdf-viewer' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					],
					'force' => [
						'description' => __( 'Force regeneration even if already processed.', 'maxtdesign-pdf-viewer' ),
						'type'        => 'boolean',
						'default'     => false,
					],
				],
			]
		);

		// Get server capabilities
		register_rest_route(
			self::NAMESPACE,
			'/capabilities',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_capabilities' ],
				'permission_callback' => [ $this, 'admin_permissions' ],
			]
		);

		// Get stats
		register_rest_route(
			self::NAMESPACE,
			'/stats',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_stats' ],
				'permission_callback' => [ $this, 'admin_permissions' ],
			]
		);
	}

	/**
	 * Get PDF info
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_pdf_info( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		$attachment = get_post( $id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'mdpv_not_found',
				__( 'Attachment not found.', 'maxtdesign-pdf-viewer' ),
				[ 'status' => 404 ]
			);
		}

		if ( 'application/pdf' !== $attachment->post_mime_type ) {
			return new WP_Error(
				'mdpv_not_pdf',
				__( 'Attachment is not a PDF.', 'maxtdesign-pdf-viewer' ),
				[ 'status' => 400 ]
			);
		}

		$data = $this->prepare_pdf_data( $id, $attachment );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Process PDF
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function process_pdf( WP_REST_Request $request ) {
		$id    = (int) $request->get_param( 'id' );
		$force = (bool) $request->get_param( 'force' );

		$attachment = get_post( $id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'mdpv_not_found',
				__( 'Attachment not found.', 'maxtdesign-pdf-viewer' ),
				[ 'status' => 404 ]
			);
		}

		if ( 'application/pdf' !== $attachment->post_mime_type ) {
			return new WP_Error(
				'mdpv_not_pdf',
				__( 'Attachment is not a PDF.', 'maxtdesign-pdf-viewer' ),
				[ 'status' => 400 ]
			);
		}

		// Check if already processed
		$processed = get_post_meta( $id, '_mdpv_processed', true );
		if ( $processed && ! $force ) {
			return new WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'PDF already processed.', 'maxtdesign-pdf-viewer' ),
					'data'    => $this->prepare_pdf_data( $id, $attachment ),
				],
				200
			);
		}

		// Process the PDF
		$extractor = $this->plugin->get_extractor();
		$result    = $extractor->process_pdf( $id, $force );

		if ( ! $result ) {
			$error = get_post_meta( $id, '_mdpv_extraction_error', true );
			return new WP_Error(
				'mdpv_processing_failed',
				$error ?: __( 'Failed to process PDF.', 'maxtdesign-pdf-viewer' ),
				[ 'status' => 500 ]
			);
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'PDF processed successfully.', 'maxtdesign-pdf-viewer' ),
				'data'    => $this->prepare_pdf_data( $id, $attachment ),
			],
			200
		);
	}

	/**
	 * Get server capabilities
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_capabilities( WP_REST_Request $request ): WP_REST_Response {
		$compatibility = $this->plugin->get_compatibility();

		return new WP_REST_Response(
			[
				'capabilities'       => $compatibility->get_capabilities(),
				'recommended_method' => $compatibility->get_recommended_method(),
			],
			200
		);
	}

	/**
	 * Get statistics
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_stats( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		// PDF stats
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_pdfs = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' AND post_mime_type = 'application/pdf'"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$processed_pdfs = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'attachment' 
				AND p.post_mime_type = 'application/pdf'
				AND pm.meta_key = %s AND pm.meta_value = '1'",
				'_mdpv_processed'
			)
		);

		// Cache stats
		$cache     = $this->plugin->get_cache();
		$cache_dir = $cache->get_cache_directory();

		$cache_files = 0;
		$cache_size  = 0;

		if ( is_dir( $cache_dir ) ) {
			$files = glob( $cache_dir . '*.webp' );
			if ( $files ) {
				$cache_files = count( $files );
				foreach ( $files as $file ) {
					$cache_size += filesize( $file );
				}
			}
		}

		return new WP_REST_Response(
			[
				'pdfs'  => [
					'total'       => $total_pdfs,
					'processed'   => $processed_pdfs,
					'unprocessed' => $total_pdfs - $processed_pdfs,
				],
				'cache' => [
					'files'          => $cache_files,
					'size'           => $cache_size,
					'size_formatted' => size_format( $cache_size ),
				],
			],
			200
		);
	}

	/**
	 * Prepare PDF data for response
	 *
	 * @param int      $id         Attachment ID.
	 * @param \WP_Post $attachment Attachment post object.
	 * @return array Prepared data.
	 */
	private function prepare_pdf_data( int $id, \WP_Post $attachment ): array {
		$cache       = $this->plugin->get_cache();
		$preview_url = $cache->get_preview_url( $id );

		return [
			'id'          => $id,
			'title'       => $attachment->post_title,
			'url'         => wp_get_attachment_url( $id ),
			'filename'    => basename( get_attached_file( $id ) ),
			'processed'   => (bool) get_post_meta( $id, '_mdpv_processed', true ),
			'page_count'  => (int) get_post_meta( $id, '_mdpv_page_count', true ) ?: null,
			'width'       => (int) get_post_meta( $id, '_mdpv_width', true ) ?: null,
			'height'      => (int) get_post_meta( $id, '_mdpv_height', true ) ?: null,
			'preview_url' => $preview_url,
			'metadata'    => [
				'pdf_title'  => get_post_meta( $id, '_mdpv_title', true ) ?: null,
				'pdf_author' => get_post_meta( $id, '_mdpv_author', true ) ?: null,
			],
			'extraction'  => [
				'method'    => get_post_meta( $id, '_mdpv_extraction_method', true ) ?: null,
				'generated' => get_post_meta( $id, '_mdpv_preview_generated', true ) ?: null,
				'error'     => get_post_meta( $id, '_mdpv_extraction_error', true ) ?: null,
			],
		];
	}

	/**
	 * Permission callback for getting PDF info
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function get_pdf_permissions( WP_REST_Request $request ): bool {
		$id = (int) $request->get_param( 'id' );

		// Check if attachment is public
		$attachment = get_post( $id );
		if ( ! $attachment ) {
			return false;
		}

		// If published, anyone can view
		if ( 'inherit' === $attachment->post_status ) {
			$parent = $attachment->post_parent ? get_post( $attachment->post_parent ) : null;
			if ( ! $parent || 'publish' === $parent->post_status ) {
				return true;
			}
		}

		// Otherwise, check if user can edit
		return current_user_can( 'edit_post', $id );
	}

	/**
	 * Permission callback for processing PDF
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function process_pdf_permissions( WP_REST_Request $request ): bool {
		return current_user_can( 'upload_files' );
	}

	/**
	 * Permission callback for admin endpoints
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function admin_permissions( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get PDF schema
	 *
	 * @return array Schema definition.
	 */
	public function get_pdf_schema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'pdf',
			'type'       => 'object',
			'properties' => [
				'id'          => [
					'description' => __( 'Attachment ID.', 'maxtdesign-pdf-viewer' ),
					'type'        => 'integer',
					'readonly'    => true,
				],
				'title'       => [
					'description' => __( 'Attachment title.', 'maxtdesign-pdf-viewer' ),
					'type'        => 'string',
				],
				'url'         => [
					'description' => __( 'PDF file URL.', 'maxtdesign-pdf-viewer' ),
					'type'        => 'string',
					'format'      => 'uri',
				],
				'processed'   => [
					'description' => __( 'Whether preview has been generated.', 'maxtdesign-pdf-viewer' ),
					'type'        => 'boolean',
				],
				'page_count'  => [
					'description' => __( 'Number of pages in PDF.', 'maxtdesign-pdf-viewer' ),
					'type'        => [ 'integer', 'null' ],
				],
				'preview_url' => [
					'description' => __( 'Preview image URL.', 'maxtdesign-pdf-viewer' ),
					'type'        => [ 'string', 'null' ],
					'format'      => 'uri',
				],
			],
		];
	}
}

