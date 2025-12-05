/**
 * PDF Viewer Block - Editor Component
 *
 * @package MaxtDesign\PDFViewer
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Button,
	SelectControl,
	ToggleControl,
	TextControl,
	Placeholder,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Get localized strings
 */
const getI18n = ( key, fallback = '' ) => {
	return window.mdpvBlockData?.i18n?.[ key ] || fallback;
};

/**
 * Get default settings
 */
const getDefault = ( key, fallback ) => {
	return window.mdpvBlockData?.defaults?.[ key ] ?? fallback;
};

/**
 * Edit component
 *
 * @param {Object} props Block props
 * @return {JSX.Element} Editor element
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		pdfId,
		pdfUrl,
		width,
		loadBehavior,
		showToolbar,
		showDownload,
		showPrint,
		showFullscreen,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'mdpv-block-editor',
	} );

	// Get PDF attachment data
	const { pdfData, previewUrl, isResolving } = useSelect(
		( select ) => {
			if ( ! pdfId ) {
				return { pdfData: null, previewUrl: null, isResolving: false };
			}

			const { getMedia } = select( 'core' );
			const { isResolving: checkResolving } = select( 'core/data' );

			const media = getMedia( pdfId );
			const resolving = checkResolving( 'core', 'getMedia', [ pdfId ] );

			let preview = null;
			if ( media?.meta?._mdpv_preview_path ) {
				const uploadsUrl = window.mdpvBlockData?.uploadsUrl || '/wp-content/uploads';
				preview = `${uploadsUrl}/${media.meta._mdpv_preview_path}`;
			}

			return {
				pdfData: media,
				previewUrl: preview,
				isResolving: resolving,
			};
		},
		[ pdfId ]
	);

	/**
	 * Handle PDF selection from media library
	 *
	 * @param {Object} media Selected media object
	 */
	const onSelectPDF = ( media ) => {
		if ( media && media.mime === 'application/pdf' ) {
			setAttributes( {
				pdfId: media.id,
				pdfUrl: media.url,
			} );
		}
	};

	/**
	 * Handle PDF removal
	 */
	const onRemovePDF = () => {
		setAttributes( {
			pdfId: 0,
			pdfUrl: '',
		} );
	};

	/**
	 * Load behavior options
	 */
	const loadBehaviorOptions = [
		{ label: getI18n( 'onClick', 'On Click' ), value: 'click' },
		{ label: getI18n( 'whenVisible', 'When Visible' ), value: 'visible' },
		{ label: getI18n( 'immediately', 'Immediately' ), value: 'immediate' },
	];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ getI18n( 'displaySettings', 'Display Settings' ) }>
					<TextControl
						label={ getI18n( 'width', 'Width' ) }
						value={ width }
						onChange={ ( value ) => setAttributes( { width: value } ) }
						help={ getI18n( 'widthHelp', 'CSS value: 100%, 800px, etc.' ) }
					/>

					<SelectControl
						label={ getI18n( 'loadBehavior', 'Load Behavior' ) }
						value={ loadBehavior }
						options={ loadBehaviorOptions }
						onChange={ ( value ) => setAttributes( { loadBehavior: value } ) }
						help={ getI18n( 'loadBehaviorHelp', 'When to load the interactive viewer' ) }
					/>
				</PanelBody>

				<PanelBody 
					title={ getI18n( 'toolbarSettings', 'Toolbar Options' ) } 
					initialOpen={ false }
				>
					<ToggleControl
						label={ getI18n( 'showToolbar', 'Show Toolbar' ) }
						checked={ showToolbar }
						onChange={ ( value ) => setAttributes( { showToolbar: value } ) }
					/>

					{ showToolbar && (
						<>
							<ToggleControl
								label={ getI18n( 'downloadButton', 'Download Button' ) }
								checked={ showDownload }
								onChange={ ( value ) => setAttributes( { showDownload: value } ) }
							/>

							<ToggleControl
								label={ getI18n( 'printButton', 'Print Button' ) }
								checked={ showPrint }
								onChange={ ( value ) => setAttributes( { showPrint: value } ) }
							/>

							<ToggleControl
								label={ getI18n( 'fullscreenButton', 'Fullscreen Button' ) }
								checked={ showFullscreen }
								onChange={ ( value ) => setAttributes( { showFullscreen: value } ) }
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ ! pdfId ? (
					<MediaUploadCheck>
						<Placeholder
							icon="pdf"
							label={ getI18n( 'title', 'PDF Viewer' ) }
							instructions={ getI18n( 'uploadPdf', 'Upload a PDF or select one from your media library.' ) }
						>
							<MediaUpload
								onSelect={ onSelectPDF }
								allowedTypes={ [ 'application/pdf' ] }
								render={ ( { open } ) => (
									<Button 
										variant="primary" 
										onClick={ open }
									>
										{ getI18n( 'selectPdf', 'Select PDF' ) }
									</Button>
								) }
							/>
						</Placeholder>
					</MediaUploadCheck>
				) : (
					<div className="mdpv-block-preview">
						{ isResolving ? (
							<div className="mdpv-block-loading">
								<Spinner />
							</div>
						) : (
							<>
								{ previewUrl ? (
									<img
										src={ previewUrl }
										alt={ pdfData?.title?.rendered || 'PDF Preview' }
										className="mdpv-block-preview-image"
									/>
								) : (
									<div className="mdpv-block-placeholder">
										<svg viewBox="0 0 24 24" className="mdpv-block-placeholder-icon">
											<path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9.5 8.5c0 .8-.7 1.5-1.5 1.5H7v2H5.5V9H8c.8 0 1.5.7 1.5 1.5v1zm5 2c0 .8-.7 1.5-1.5 1.5h-2.5V9H13c.8 0 1.5.7 1.5 1.5v3zm4-3H17v1h1.5V13H17v2h-1.5V9h3v1.5zM7 10.5h1v1H7v-1zm5 0h1v3h-1v-3z" fill="currentColor"/>
										</svg>
									</div>
								) }

								<div className="mdpv-block-info">
									<span className="mdpv-block-filename">
										{ pdfData?.title?.rendered || 'PDF Document' }
									</span>
									{ pdfData?.meta?._mdpv_page_count && (
										<span className="mdpv-block-pages">
											{ pdfData.meta._mdpv_page_count } { getI18n( 'pages', 'pages' ) }
										</span>
									) }
								</div>

								<div className="mdpv-block-actions">
									<MediaUploadCheck>
										<MediaUpload
											onSelect={ onSelectPDF }
											allowedTypes={ [ 'application/pdf' ] }
											render={ ( { open } ) => (
												<Button 
													variant="secondary" 
													onClick={ open }
													size="small"
												>
													{ getI18n( 'replacePdf', 'Replace' ) }
												</Button>
											) }
										/>
									</MediaUploadCheck>

									<Button
										variant="link"
										isDestructive
										onClick={ onRemovePDF }
										size="small"
									>
										{ getI18n( 'removePdf', 'Remove' ) }
									</Button>
								</div>
							</>
						) }
					</div>
				) }
			</div>
		</>
	);
}

