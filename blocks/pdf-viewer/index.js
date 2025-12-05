/**
 * PDF Viewer Block - Registration
 *
 * @package MaxtDesign\PDFViewer
 * @since 1.0.0
 */

// Import editor styles
import './index.scss';

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import Edit from './edit';
import metadata from './block.json';

/**
 * PDF icon for block
 */
const PdfIcon = () => (
	<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
		<path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9.5 8.5c0 .8-.7 1.5-1.5 1.5H7v2H5.5V9H8c.8 0 1.5.7 1.5 1.5v1zm5 2c0 .8-.7 1.5-1.5 1.5h-2.5V9H13c.8 0 1.5.7 1.5 1.5v3zm4-3H17v1h1.5V13H17v2h-1.5V9h3v1.5zM7 10.5h1v1H7v-1zm5 0h1v3h-1v-3z" fill="currentColor"/>
	</svg>
);

/**
 * Register the block
 */
registerBlockType( metadata.name, {
	...metadata,
	icon: PdfIcon,
	edit: Edit,
	save: () => null, // Server-side rendering
} );
