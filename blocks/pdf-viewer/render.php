<?php
/**
 * Server-side rendering for PDF Viewer block
 *
 * @package MaxtDesign\PDFViewer
 * @since 1.0.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for dynamic blocks).
 * @var WP_Block $block      Block instance.
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

// Use the Renderer class
if ( class_exists( 'MaxtDesign\PDFViewer\Renderer' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer class
	echo MaxtDesign\PDFViewer\Renderer::render( $attributes );
} else {
	echo '<p>' . esc_html__( 'PDF Viewer plugin not properly loaded.', 'maxtdesign-pdf-viewer' ) . '</p>';
}

