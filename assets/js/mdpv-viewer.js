/**
 * MaxtDesign PDF Viewer - Full Viewer Module
 *
 * Handles PDF.js initialization, document loading, and page rendering.
 * Dynamically imported by mdpv-loader.js when viewer is activated.
 *
 * @package MaxtDesign\PDFViewer
 * @since 1.0.0
 */

/**
 * CSS class names
 * @type {Object}
 */
const CLASSES = {
	viewer: 'mdpv-viewer',
	active: 'mdpv-active',
	loading: 'mdpv-loading',
	loadingActive: 'mdpv-loading-active',
	preview: 'mdpv-preview',
	activate: 'mdpv-activate',
	canvasContainer: 'mdpv-canvas-container',
	canvas: 'mdpv-canvas',
	toolbar: 'mdpv-toolbar',
	toolbarGroup: 'mdpv-toolbar-group',
	toolbarBtn: 'mdpv-toolbar-btn',
	toolbarPageInfo: 'mdpv-toolbar-page-info',
	toolbarPageInput: 'mdpv-toolbar-page-input',
	toolbarZoom: 'mdpv-toolbar-zoom',
	toolbarZoomLabel: 'mdpv-toolbar-zoom-label',
	fullscreen: 'mdpv-fullscreen',
	errorContainer: 'mdpv-error-container',
	errorMessage: 'mdpv-error-message',
};

/**
 * Default zoom levels
 * @type {number[]}
 */
const ZOOM_LEVELS = [ 0.5, 0.75, 1, 1.25, 1.5, 2, 3 ];

/**
 * Default configuration
 * @type {Object}
 */
const DEFAULT_CONFIG = {
	pdfUrl: '',
	pdfId: 0,
	pageCount: 1,
	showToolbar: true,
	showDownload: true,
	showPrint: true,
	showFullscreen: true,
	workerUrl: '',
	i18n: {},
};

/**
 * PDF.js library reference (loaded dynamically)
 * @type {Object|null}
 */
let pdfjsLib = null;

/**
 * MDPVViewer Class
 *
 * Main viewer class that handles PDF rendering and interaction.
 */
export class MDPVViewer {
	/**
	 * Create viewer instance
	 *
	 * @param {HTMLElement} container - Viewer container element
	 * @param {Object} config - Viewer configuration
	 */
	constructor( container, config = {} ) {
		/** @type {HTMLElement} */
		this.container = container;

		/** @type {Object} */
		this.config = { ...DEFAULT_CONFIG, ...config };

		/** @type {Object|null} PDF.js document instance */
		this.pdfDoc = null;

		/** @type {number} Current page number (1-indexed) */
		this.currentPage = 1;

		/** @type {number} Total page count */
		this.totalPages = this.config.pageCount || 1;

		/** @type {number} Current zoom level */
		this.scale = 1;

		/** @type {number} Current zoom level index */
		this.zoomIndex = Math.max( 0, ZOOM_LEVELS.indexOf( 1 ) );

		/** @type {boolean} Is currently rendering */
		this.isRendering = false;

		/** @type {Object|null} Current render task */
		this.renderTask = null;

		/** @type {HTMLCanvasElement|null} */
		this.canvas = null;

		/** @type {CanvasRenderingContext2D|null} */
		this.ctx = null;

		/** @type {HTMLElement|null} */
		this.canvasContainer = null;

		/** @type {HTMLElement|null} */
		this.toolbar = null;

		/** @type {boolean} Is in fullscreen mode */
		this.isFullscreen = false;

		/** @type {AbortController|null} For cleanup */
		this.abortController = new AbortController();

		// Initialize
		this.init();
	}

	/**
	 * Initialize the viewer
	 */
	async init() {
		try {
			// Load PDF.js library
			await this.loadPdfJs();

			// Set up the viewer UI
			this.setupUI();

			// Load the PDF document
			await this.loadDocument();

			// Render first page
			await this.renderPage( this.currentPage );

			// Mark as active
			this.container.classList.add( CLASSES.active );
			this.container.classList.remove( CLASSES.loadingActive );

			// Hide loading indicator
			this.hideLoading();

			// Set up event listeners
			this.setupEventListeners();

			// Focus for keyboard navigation
			this.container.setAttribute( 'tabindex', '0' );
			this.container.setAttribute( 'role', 'application' );
			this.container.setAttribute( 'aria-label', this.config.i18n.viewerLabel || 'PDF Viewer - Use arrow keys to navigate pages' );
			
			// Focus the container
			this.container.focus({ preventScroll: true });

			// Re-focus on click anywhere in viewer
			this.container.addEventListener( 'click', () => {
				if ( document.activeElement !== this.container && 
					 ! this.container.contains( document.activeElement ) ) {
					this.container.focus({ preventScroll: true });
				}
			}, { signal: this.abortController.signal } );
		} catch ( error ) {
			console.error( '[MDPV Viewer] Initialization failed:', error );
			this.showError( error.message || this.config.i18n.error || 'Failed to load document' );
		}
	}

	/**
	 * Load PDF.js library dynamically
	 */
	async loadPdfJs() {
		if ( pdfjsLib ) {
			return; // Already loaded
		}

		// Try to get PDF.js URL from config, or construct from plugin URL
		let pdfJsUrl = this.config.pdfjsUrl;
		if ( ! pdfJsUrl ) {
			const pluginUrl = this.config.pluginUrl || window.mdpvConfig?.pluginUrl || '';
			pdfJsUrl = pluginUrl + 'vendor/pdfjs/pdf.min.mjs';
		}

		try {
			const module = await import( pdfJsUrl );
			pdfjsLib = module;

			// Set worker path
			let workerUrl = this.config.workerUrl;
			if ( ! workerUrl ) {
				// Try to construct from pdfjsUrl or pluginUrl
				if ( this.config.pdfjsUrl ) {
					workerUrl = this.config.pdfjsUrl.replace( 'pdf.min.mjs', 'pdf.worker.min.mjs' );
				} else {
					const pluginUrl = this.config.pluginUrl || window.mdpvConfig?.pluginUrl || '';
					workerUrl = pluginUrl + 'vendor/pdfjs/pdf.worker.min.mjs';
				}
			}
			pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;
		} catch ( error ) {
			console.error( '[MDPV Viewer] Failed to load PDF.js:', error );
			throw new Error( this.config.i18n.loadError || 'Failed to load PDF viewer library' );
		}
	}

	/**
	 * Load the PDF document
	 */
	async loadDocument() {
		if ( ! this.config.pdfUrl ) {
			throw new Error( 'No PDF URL provided' );
		}

		try {
			const loadingTask = pdfjsLib.getDocument( {
				url: this.config.pdfUrl,
				cMapUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/cmaps/',
				cMapPacked: true,
			} );

			this.pdfDoc = await loadingTask.promise;
			this.totalPages = this.pdfDoc.numPages;

			// Update toolbar if already created
			this.updateToolbarPageInfo();
		} catch ( error ) {
			console.error( '[MDPV Viewer] Failed to load document:', error );

			if ( error.name === 'PasswordException' ) {
				throw new Error( this.config.i18n.passwordRequired || 'This PDF requires a password' );
			}

			throw new Error( this.config.i18n.error || 'Failed to load document' );
		}
	}

	/**
	 * Set up viewer UI elements
	 */
	setupUI() {
		// Create canvas container
		this.canvasContainer = document.createElement( 'div' );
		this.canvasContainer.className = CLASSES.canvasContainer;

		// Create canvas
		this.canvas = document.createElement( 'canvas' );
		this.canvas.className = CLASSES.canvas;
		this.ctx = this.canvas.getContext( '2d' );

		this.canvasContainer.appendChild( this.canvas );
		this.container.appendChild( this.canvasContainer );

		// Create toolbar if enabled
		if ( this.config.showToolbar ) {
			this.createToolbar();
		}
	}

	/**
	 * Create toolbar element
	 */
	createToolbar() {
		this.toolbar = document.createElement( 'div' );
		this.toolbar.className = CLASSES.toolbar;
		this.toolbar.setAttribute( 'role', 'toolbar' );
		this.toolbar.setAttribute( 'aria-label', this.config.i18n.toolbar || 'PDF Controls' );

		// Left group: Navigation
		const navGroup = this.createToolbarGroup();
		
		// Previous page button
		navGroup.appendChild( this.createToolbarButton(
			'prev',
			this.config.i18n.prevPage || 'Previous page',
			this.getIcon( 'chevronLeft' ),
			() => this.prevPage()
		) );

		// Page info
		navGroup.appendChild( this.createPageInfo() );

		// Next page button
		navGroup.appendChild( this.createToolbarButton(
			'next',
			this.config.i18n.nextPage || 'Next page',
			this.getIcon( 'chevronRight' ),
			() => this.nextPage()
		) );

		this.toolbar.appendChild( navGroup );

		// Center group: Zoom
		const zoomGroup = this.createToolbarGroup();
		zoomGroup.classList.add( CLASSES.toolbarZoom );

		// Zoom out button
		zoomGroup.appendChild( this.createToolbarButton(
			'zoomOut',
			this.config.i18n.zoomOut || 'Zoom out',
			this.getIcon( 'minus' ),
			() => this.zoomOut()
		) );

		// Zoom label
		const zoomLabel = document.createElement( 'span' );
		zoomLabel.className = CLASSES.toolbarZoomLabel;
		zoomLabel.id = 'mdpv-zoom-label-' + this.config.pdfId;
		zoomLabel.textContent = Math.round( this.scale * 100 ) + '%';
		zoomGroup.appendChild( zoomLabel );
		this.zoomLabel = zoomLabel;

		// Zoom in button
		zoomGroup.appendChild( this.createToolbarButton(
			'zoomIn',
			this.config.i18n.zoomIn || 'Zoom in',
			this.getIcon( 'plus' ),
			() => this.zoomIn()
		) );

		this.toolbar.appendChild( zoomGroup );

		// Right group: Actions
		const actionsGroup = this.createToolbarGroup();

		// Download button
		if ( this.config.showDownload ) {
			actionsGroup.appendChild( this.createToolbarButton(
				'download',
				this.config.i18n.download || 'Download',
				this.getIcon( 'download' ),
				() => this.download()
			) );
		}

		// Print button
		if ( this.config.showPrint ) {
			actionsGroup.appendChild( this.createToolbarButton(
				'print',
				this.config.i18n.print || 'Print',
				this.getIcon( 'print' ),
				() => this.print()
			) );
		}

		// Fullscreen button
		if ( this.config.showFullscreen ) {
			const fsBtn = this.createToolbarButton(
				'fullscreen',
				this.config.i18n.fullscreen || 'Fullscreen',
				this.getIcon( 'fullscreen' ),
				() => this.toggleFullscreen()
			);
			this.fullscreenBtn = fsBtn;
			actionsGroup.appendChild( fsBtn );
		}

		this.toolbar.appendChild( actionsGroup );

		// Add to container
		this.container.appendChild( this.toolbar );

		// Initialize button states
		this.updateNavigationButtons();
		this.updateZoomLabel();
	}

	/**
	 * Create toolbar button group
	 *
	 * @returns {HTMLElement} Group element
	 */
	createToolbarGroup() {
		const group = document.createElement( 'div' );
		group.className = CLASSES.toolbarGroup;
		group.setAttribute( 'role', 'group' );
		return group;
	}

	/**
	 * Create toolbar button
	 *
	 * @param {string} name - Button name for identification
	 * @param {string} label - Accessible label
	 * @param {string} iconSvg - SVG icon HTML
	 * @param {Function} onClick - Click handler
	 * @returns {HTMLButtonElement} Button element
	 */
	createToolbarButton( name, label, iconSvg, onClick ) {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = CLASSES.toolbarBtn;
		button.setAttribute( 'aria-label', label );
		button.setAttribute( 'title', label );
		button.dataset.action = name;
		button.innerHTML = iconSvg;

		button.addEventListener( 'click', onClick, { 
			signal: this.abortController.signal 
		} );

		return button;
	}

	/**
	 * Create page info element
	 *
	 * @returns {HTMLElement} Page info element
	 */
	createPageInfo() {
		const pageInfo = document.createElement( 'div' );
		pageInfo.className = CLASSES.toolbarPageInfo;

		// Page input
		const pageInput = document.createElement( 'input' );
		pageInput.type = 'number';
		pageInput.className = CLASSES.toolbarPageInput;
		pageInput.min = '1';
		pageInput.max = String( this.totalPages );
		pageInput.value = String( this.currentPage );
		pageInput.setAttribute( 'aria-label', this.config.i18n.page || 'Page number' );
		
		pageInput.addEventListener( 'change', ( e ) => {
			this.goToPage( e.target.value );
		}, { signal: this.abortController.signal } );

		pageInput.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' ) {
				this.goToPage( e.target.value );
				e.target.blur();
			}
		}, { signal: this.abortController.signal } );

		this.pageInput = pageInput;

		// "of X" text
		const ofText = document.createElement( 'span' );
		ofText.textContent = ` ${this.config.i18n.of || 'of'} `;

		const totalSpan = document.createElement( 'span' );
		totalSpan.textContent = String( this.totalPages );
		this.totalPagesSpan = totalSpan;

		pageInfo.appendChild( pageInput );
		pageInfo.appendChild( ofText );
		pageInfo.appendChild( totalSpan );

		return pageInfo;
	}

	/**
	 * Render a specific page
	 *
	 * @param {number} pageNum - Page number (1-indexed)
	 */
	async renderPage( pageNum ) {
		if ( ! this.pdfDoc || this.isRendering ) {
			return;
		}

		// Validate page number
		pageNum = Math.max( 1, Math.min( pageNum, this.totalPages ) );
		this.currentPage = pageNum;

		this.isRendering = true;

		try {
			// Cancel any existing render
			if ( this.renderTask ) {
				this.renderTask.cancel();
				this.renderTask = null;
			}

			// Get the page
			const page = await this.pdfDoc.getPage( pageNum );

			// Calculate scale based on container width
			const containerWidth = this.canvasContainer.clientWidth;
			const viewport = page.getViewport( { scale: 1 } );

			// Calculate scale to fit width, then apply zoom
			const baseScale = containerWidth / viewport.width;
			const scaledViewport = page.getViewport( { scale: baseScale * this.scale } );

			// Set canvas dimensions
			this.canvas.width = scaledViewport.width;
			this.canvas.height = scaledViewport.height;

			// Render the page
			const renderContext = {
				canvasContext: this.ctx,
				viewport: scaledViewport,
			};

			this.renderTask = page.render( renderContext );
			await this.renderTask.promise;

			this.renderTask = null;

			// Update toolbar
			this.updateToolbarPageInfo();
		} catch ( error ) {
			if ( error.name !== 'RenderingCancelledException' ) {
				console.error( '[MDPV Viewer] Render failed:', error );
			}
		} finally {
			this.isRendering = false;
		}
	}

	/**
	 * Go to previous page
	 */
	prevPage() {
		if ( this.currentPage > 1 ) {
			this.renderPage( this.currentPage - 1 );
		}
	}

	/**
	 * Go to next page
	 */
	nextPage() {
		if ( this.currentPage < this.totalPages ) {
			this.renderPage( this.currentPage + 1 );
		}
	}

	/**
	 * Go to specific page
	 *
	 * @param {number} pageNum - Page number
	 */
	goToPage( pageNum ) {
		const num = parseInt( pageNum, 10 );
		if ( ! isNaN( num ) && num >= 1 && num <= this.totalPages ) {
			this.renderPage( num );
		}
	}

	/**
	 * Zoom in
	 */
	zoomIn() {
		if ( this.zoomIndex < ZOOM_LEVELS.length - 1 ) {
			this.zoomIndex++;
			this.scale = ZOOM_LEVELS[ this.zoomIndex ];
			this.renderPage( this.currentPage );
			this.updateZoomLabel();
		}
	}

	/**
	 * Zoom out
	 */
	zoomOut() {
		if ( this.zoomIndex > 0 ) {
			this.zoomIndex--;
			this.scale = ZOOM_LEVELS[ this.zoomIndex ];
			this.renderPage( this.currentPage );
			this.updateZoomLabel();
		}
	}

	/**
	 * Set specific zoom level
	 *
	 * @param {number} scale - Zoom scale (0.5 to 3)
	 */
	setZoom( scale ) {
		this.scale = Math.max( 0.5, Math.min( 3, scale ) );
		// Find closest zoom level index
		const foundIndex = ZOOM_LEVELS.findIndex( z => z >= this.scale );
		this.zoomIndex = foundIndex >= 0 ? foundIndex : ZOOM_LEVELS.length - 1;
		this.renderPage( this.currentPage );
		this.updateZoomLabel();
	}

	/**
	 * Download the PDF
	 */
	download() {
		const link = document.createElement( 'a' );
		link.href = this.config.pdfUrl;
		link.download = this.getFilename();
		link.click();
	}

	/**
	 * Print the PDF
	 */
	print() {
		// Open PDF in new window for printing
		const printWindow = window.open( this.config.pdfUrl, '_blank' );
		if ( printWindow ) {
			printWindow.addEventListener( 'load', () => {
				printWindow.print();
			} );
		}
	}

	/**
	 * Get SVG icon by name
	 *
	 * @param {string} name - Icon name
	 * @returns {string} SVG HTML
	 */
	getIcon( name ) {
		const icons = {
			chevronLeft: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="15 18 9 12 15 6"></polyline></svg>`,

			chevronRight: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="9 18 15 12 9 6"></polyline></svg>`,

			minus: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"></line></svg>`,

			plus: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>`,

			download: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>`,

			print: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>`,

			fullscreen: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>`,

			exitFullscreen: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="4 14 10 14 10 20"></polyline><polyline points="20 10 14 10 14 4"></polyline><line x1="14" y1="10" x2="21" y2="3"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>`,

		};

		return icons[ name ] || '';
	}

	/**
	 * Toggle fullscreen mode
	 */
	toggleFullscreen() {
		if ( this.isFullscreen ) {
			this.exitFullscreen();
		} else {
			this.enterFullscreen();
		}
	}

	/**
	 * Enter fullscreen mode
	 */
	enterFullscreen() {
		// Check if already in fullscreen
		if ( this.isFullscreen ) {
			return;
		}

		// Check if fullscreen is available
		if ( ! document.fullscreenEnabled && 
			 ! document.webkitFullscreenEnabled && 
			 ! document.msFullscreenEnabled ) {
			return;
		}

		let promise = null;

		try {
			if ( this.container.requestFullscreen ) {
				promise = this.container.requestFullscreen();
			} else if ( this.container.webkitRequestFullscreen ) {
				promise = this.container.webkitRequestFullscreen();
			} else if ( this.container.msRequestFullscreen ) {
				promise = this.container.msRequestFullscreen();
			}

			// Handle promise rejection silently (browser may reject if not user-initiated)
			if ( promise && typeof promise.catch === 'function' ) {
				promise.catch( ( error ) => {
					// Silently handle rejection - fullscreen may not be available
					// or may require a direct user interaction in some contexts
					// Don't log to console to avoid cluttering
				} );
			}
		} catch ( error ) {
			// Silently handle synchronous errors (e.g., "API can only be initiated by a user gesture")
			// The fullscreenchange event will still fire if fullscreen succeeds via other means
			// Suppress console output by not re-throwing or logging
		}
	}

	/**
	 * Exit fullscreen mode
	 */
	exitFullscreen() {
		// Check if not in fullscreen
		if ( ! this.isFullscreen ) {
			return;
		}

		let promise = null;

		try {
			if ( document.exitFullscreen ) {
				promise = document.exitFullscreen();
			} else if ( document.webkitExitFullscreen ) {
				promise = document.webkitExitFullscreen();
			} else if ( document.msExitFullscreen ) {
				promise = document.msExitFullscreen();
			}

			// Handle promise rejection silently
			if ( promise && typeof promise.catch === 'function' ) {
				promise.catch( () => {
					// Silently handle rejection
				} );
			}
		} catch ( error ) {
			// Silently handle synchronous errors
		}
	}

	/**
	 * Handle fullscreen change
	 */
	handleFullscreenChange() {
		this.isFullscreen = !! (
			document.fullscreenElement ||
			document.webkitFullscreenElement ||
			document.msFullscreenElement
		);

		this.container.classList.toggle( CLASSES.fullscreen, this.isFullscreen );

		// Update fullscreen button icon
		this.updateFullscreenButton();

		// Re-render at new size
		setTimeout( () => {
			this.renderPage( this.currentPage );
		}, 100 );
	}

	/**
	 * Update fullscreen button icon
	 */
	updateFullscreenButton() {
		if ( ! this.fullscreenBtn ) return;

		const icon = this.isFullscreen 
			? this.getIcon( 'exitFullscreen' )
			: this.getIcon( 'fullscreen' );
		
		const label = this.isFullscreen
			? ( this.config.i18n.exitFullscreen || 'Exit fullscreen' )
			: ( this.config.i18n.fullscreen || 'Fullscreen' );

		this.fullscreenBtn.innerHTML = icon;
		this.fullscreenBtn.setAttribute( 'aria-label', label );
		this.fullscreenBtn.setAttribute( 'title', label );
	}

	/**
	 * Set up event listeners
	 */
	setupEventListeners() {
		const signal = this.abortController.signal;

		// Keyboard navigation - use capture phase to intercept before browser
		this.container.addEventListener( 
			'keydown', 
			( e ) => this.handleKeydown( e ), 
			{ signal, capture: true } 
		);

		// Also listen on document for when viewer is "active" but not focused
		document.addEventListener(
			'keydown',
			( e ) => {
				// Only handle if this viewer is active and visible
				if ( this.container.classList.contains( CLASSES.active ) && this.isViewerVisible() ) {
					this.handleKeydown( e );
				}
			},
			{ signal }
		);

		// Fullscreen change events
		document.addEventListener( 'fullscreenchange', () => this.handleFullscreenChange(), { signal } );
		document.addEventListener( 'webkitfullscreenchange', () => this.handleFullscreenChange(), { signal } );
		document.addEventListener( 'msfullscreenchange', () => this.handleFullscreenChange(), { signal } );

		// Window resize - re-render at new size
		window.addEventListener( 'resize', () => this.handleResize(), { signal } );

		// Mouse wheel zoom
		this.container.addEventListener( 'wheel', ( e ) => this.handleWheel( e ), { signal, passive: false } );

		// Touch events for mobile
		this.setupTouchEvents();
	}

	/**
	 * Handle keyboard events
	 *
	 * @param {KeyboardEvent} e - Keyboard event
	 */
	handleKeydown( e ) {
		// Don't handle if focus is in input field
		if ( e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' ) {
			return;
		}

		const key = e.key;
		const code = e.code;
		const ctrlOrCmd = e.ctrlKey || e.metaKey;

		// Handle Ctrl/Cmd combinations first (must preventDefault immediately)
		if ( ctrlOrCmd ) {
			switch ( code ) {
				case 'Equal':      // Plus key (=/+)
				case 'NumpadAdd':  // Numpad +
					e.preventDefault();
					e.stopPropagation();
					this.zoomIn();
					return;

				case 'Minus':       // Minus key (-/_)
				case 'NumpadSubtract': // Numpad -
					e.preventDefault();
					e.stopPropagation();
					this.zoomOut();
					return;

				case 'Digit0':     // Zero key
				case 'Numpad0':    // Numpad 0
					e.preventDefault();
					e.stopPropagation();
					this.setZoom( 1 ); // Reset to 100%
					return;

				case 'KeyS':       // S key
					e.preventDefault();
					e.stopPropagation();
					this.download();
					return;

				case 'KeyP':       // P key
					e.preventDefault();
					e.stopPropagation();
					this.print();
					return;
			}
		}

		// Handle non-modifier keys
		switch ( key ) {
			case 'ArrowLeft':
				e.preventDefault();
				this.prevPage();
				break;

			case 'ArrowRight':
				e.preventDefault();
				this.nextPage();
				break;

			case 'ArrowUp':
				e.preventDefault();
				this.prevPage();
				break;

			case 'ArrowDown':
				e.preventDefault();
				this.nextPage();
				break;

			case 'PageUp':
				e.preventDefault();
				this.prevPage();
				break;

			case 'PageDown':
				e.preventDefault();
				this.nextPage();
				break;

			case ' ': // Space bar
				e.preventDefault();
				this.nextPage();
				break;

			case 'Home':
				e.preventDefault();
				this.goToPage( 1 );
				break;

			case 'End':
				e.preventDefault();
				this.goToPage( this.totalPages );
				break;

			case 'f':
			case 'F':
				// Only if not combined with Ctrl/Cmd (Ctrl+F is browser find)
				if ( ! ctrlOrCmd ) {
					e.preventDefault();
					e.stopPropagation();
					// Call fullscreen directly within the user gesture event handler
					// to ensure it's recognized as a user-initiated action
					this.toggleFullscreen();
				}
				break;

			case 'Escape':
				// Let browser handle Escape for exiting fullscreen
				// We just track the state change via fullscreenchange event
				break;
		}
	}

	/**
	 * Check if viewer is visible in viewport
	 *
	 * @returns {boolean} True if viewer is visible
	 */
	isViewerVisible() {
		const rect = this.container.getBoundingClientRect();
		const windowHeight = window.innerHeight || document.documentElement.clientHeight;
		const windowWidth = window.innerWidth || document.documentElement.clientWidth;

		// Check if at least 50% of the viewer is visible
		const verticalVisible = rect.top < windowHeight && rect.bottom > 0;
		const horizontalVisible = rect.left < windowWidth && rect.right > 0;

		return verticalVisible && horizontalVisible;
	}

	/**
	 * Handle window resize
	 */
	handleResize() {
		const debouncedResize = this.debounce( () => {
			if ( ! this.isRendering ) {
				this.renderPage( this.currentPage );
			}
		}, 250 );

		debouncedResize();
	}

	/**
	 * Handle mouse wheel for zoom
	 *
	 * @param {WheelEvent} e - Wheel event
	 */
	handleWheel( e ) {
		// Only zoom if Ctrl/Cmd key is held
		if ( ! ( e.ctrlKey || e.metaKey ) ) {
			return;
		}

		e.preventDefault();

		if ( e.deltaY < 0 ) {
			this.zoomIn();
		} else if ( e.deltaY > 0 ) {
			this.zoomOut();
		}
	}

	/**
	 * Set up touch events for mobile navigation and zoom
	 */
	setupTouchEvents() {
		const signal = this.abortController.signal;

		let touchStartDistance = 0;
		let touchStartScale = this.scale;
		let lastTouchTime = 0;
		let touchStartX = 0;
		let touchStartY = 0;

		// Handle touch start
		this.container.addEventListener( 'touchstart', ( e ) => {
			if ( e.touches.length === 2 ) {
				// Pinch zoom
				const touch1 = e.touches[ 0 ];
				const touch2 = e.touches[ 1 ];
				touchStartDistance = Math.hypot(
					touch2.clientX - touch1.clientX,
					touch2.clientY - touch1.clientY
				);
				touchStartScale = this.scale;
			} else if ( e.touches.length === 1 ) {
				// Single touch for swipe
				touchStartX = e.touches[ 0 ].clientX;
				touchStartY = e.touches[ 0 ].clientY;
				lastTouchTime = Date.now();
			}
		}, { signal, passive: true } );

		// Handle touch move
		this.container.addEventListener( 'touchmove', ( e ) => {
			if ( e.touches.length === 2 ) {
				// Pinch zoom
				e.preventDefault();
				const touch1 = e.touches[ 0 ];
				const touch2 = e.touches[ 1 ];
				const currentDistance = Math.hypot(
					touch2.clientX - touch1.clientX,
					touch2.clientY - touch1.clientY
				);

				const scaleChange = currentDistance / touchStartDistance;
				const newScale = touchStartScale * scaleChange;
				this.setZoom( newScale );
			}
		}, { signal, passive: false } );

		// Handle touch end
		this.container.addEventListener( 'touchend', ( e ) => {
			if ( e.changedTouches.length === 1 && e.touches.length === 0 ) {
				// Single touch ended - check for swipe
				const touch = e.changedTouches[ 0 ];
				const deltaX = touch.clientX - touchStartX;
				const deltaY = touch.clientY - touchStartY;
				const deltaTime = Date.now() - lastTouchTime;

				// Swipe detection: horizontal movement > 50px and < 300ms
				if ( Math.abs( deltaX ) > 50 && Math.abs( deltaY ) < 50 && deltaTime < 300 ) {
					if ( deltaX > 0 ) {
						this.prevPage();
					} else {
						this.nextPage();
					}
				}
			}
		}, { signal, passive: true } );
	}

	/**
	 * Update toolbar page info
	 */
	updateToolbarPageInfo() {
		if ( this.pageInput ) {
			this.pageInput.value = String( this.currentPage );
			this.pageInput.max = String( this.totalPages );
		}

		if ( this.totalPagesSpan ) {
			this.totalPagesSpan.textContent = String( this.totalPages );
		}

		// Update button states
		this.updateNavigationButtons();
	}

	/**
	 * Update navigation button states
	 */
	updateNavigationButtons() {
		const prevBtn = this.toolbar?.querySelector( '[data-action="prev"]' );
		const nextBtn = this.toolbar?.querySelector( '[data-action="next"]' );

		if ( prevBtn ) {
			prevBtn.disabled = this.currentPage <= 1;
		}

		if ( nextBtn ) {
			nextBtn.disabled = this.currentPage >= this.totalPages;
		}
	}

	/**
	 * Update zoom label
	 */
	updateZoomLabel() {
		if ( this.zoomLabel ) {
			this.zoomLabel.textContent = Math.round( this.scale * 100 ) + '%';
		}

		// Update button states
		const zoomOutBtn = this.toolbar?.querySelector( '[data-action="zoomOut"]' );
		const zoomInBtn = this.toolbar?.querySelector( '[data-action="zoomIn"]' );

		if ( zoomOutBtn ) {
			zoomOutBtn.disabled = this.zoomIndex <= 0;
		}

		if ( zoomInBtn ) {
			zoomInBtn.disabled = this.zoomIndex >= ZOOM_LEVELS.length - 1;
		}
	}

	/**
	 * Get filename from URL
	 *
	 * @returns {string} Filename
	 */
	getFilename() {
		try {
			const url = new URL( this.config.pdfUrl );
			const pathname = url.pathname;
			return pathname.substring( pathname.lastIndexOf( '/' ) + 1 ) || 'document.pdf';
		} catch {
			return 'document.pdf';
		}
	}

	/**
	 * Hide loading indicator
	 */
	hideLoading() {
		const loadingEl = this.container.querySelector( '.' + CLASSES.loading );
		if ( loadingEl ) {
			loadingEl.hidden = true;
		}
	}

	/**
	 * Show error message
	 *
	 * @param {string} message - Error message
	 */
	showError( message ) {
		this.container.classList.remove( CLASSES.loadingActive );
		this.hideLoading();

		let errorContainer = this.container.querySelector( '.' + CLASSES.errorContainer );
		if ( errorContainer ) {
			errorContainer.innerHTML = `<div class="${CLASSES.errorMessage}">${this.escapeHtml( message )}</div>`;
			errorContainer.hidden = false;
		}
	}

	/**
	 * Escape HTML
	 *
	 * @param {string} text - Text to escape
	 * @returns {string} Escaped text
	 */
	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Debounce function calls
	 *
	 * @param {Function} func - Function to debounce
	 * @param {number} wait - Wait time in milliseconds
	 * @returns {Function} Debounced function
	 */
	debounce( func, wait ) {
		let timeout;
		return function executedFunction( ...args ) {
			const later = () => {
				clearTimeout( timeout );
				func.apply( this, args );
			};
			clearTimeout( timeout );
			timeout = setTimeout( later, wait );
		};
	}

	/**
	 * Announce message to screen readers
	 *
	 * @param {string} message - Message to announce
	 */
	announce( message ) {
		const announcement = document.createElement( 'div' );
		announcement.setAttribute( 'role', 'status' );
		announcement.setAttribute( 'aria-live', 'polite' );
		announcement.setAttribute( 'aria-atomic', 'true' );
		announcement.className = 'mdpv-sr-only';
		announcement.textContent = message;

		document.body.appendChild( announcement );

		setTimeout( () => {
			if ( announcement.parentNode ) {
				announcement.parentNode.removeChild( announcement );
			}
		}, 1000 );
	}

	/**
	 * Clean up all resources
	 *
	 * Call this before removing the viewer from DOM
	 */
	destroy() {
		// Abort all event listeners
		if ( this.abortController ) {
			this.abortController.abort();
			this.abortController = null;
		}

		// Cancel any active render
		if ( this.renderTask ) {
			this.renderTask.cancel();
			this.renderTask = null;
		}

		// Destroy PDF document
		if ( this.pdfDoc ) {
			this.pdfDoc.destroy();
			this.pdfDoc = null;
		}

		// Clear canvas
		if ( this.ctx && this.canvas ) {
			this.ctx.clearRect( 0, 0, this.canvas.width, this.canvas.height );
		}

		// Remove added elements
		if ( this.canvasContainer && this.canvasContainer.parentNode ) {
			this.canvasContainer.parentNode.removeChild( this.canvasContainer );
		}

		if ( this.toolbar && this.toolbar.parentNode ) {
			this.toolbar.parentNode.removeChild( this.toolbar );
		}

		// Remove classes
		this.container.classList.remove( CLASSES.active, CLASSES.loadingActive, CLASSES.fullscreen );

		// Clear references
		this.canvas = null;
		this.ctx = null;
		this.canvasContainer = null;
		this.toolbar = null;
		this.pageInput = null;
		this.totalPagesSpan = null;
		this.zoomLabel = null;
		this.fullscreenBtn = null;

		// Remove tabindex
		this.container.removeAttribute( 'tabindex' );

		console.log( '[MDPV Viewer] Destroyed' );
	}

	/**
	 * Static method to test viewer with a PDF URL
	 * For development/debugging only
	 *
	 * @param {string} pdfUrl - URL to PDF file
	 * @param {HTMLElement|string} container - Container element or selector
	 * @returns {MDPVViewer} Viewer instance
	 */
	static test( pdfUrl, container = null ) {
		// Create container if not provided
		if ( ! container ) {
			container = document.createElement( 'div' );
			container.style.cssText = 'width:100%;max-width:800px;margin:20px auto;aspect-ratio:612/792;';
			container.className = CLASSES.viewer;
			document.body.appendChild( container );
		} else if ( typeof container === 'string' ) {
			container = document.querySelector( container );
		}

		if ( ! container ) {
			console.error( '[MDPV] Test: Container not found' );
			return null;
		}

		// Add required elements
		container.innerHTML = `
			<div class="${CLASSES.loading}">
				<div class="mdpv-spinner"></div>
				<span>Loading...</span>
			</div>
			<div class="${CLASSES.errorContainer}" hidden></div>
		`;

		// Create viewer
		const viewer = new MDPVViewer( container, {
			pdfUrl: pdfUrl,
			pdfId: 0,
			pageCount: 1,
			showToolbar: true,
			showDownload: true,
			showPrint: true,
			showFullscreen: true,
			pluginUrl: '', // Will need to be set for PDF.js
			workerUrl: '',
			i18n: {
				loading: 'Loading...',
				error: 'Failed to load',
				page: 'Page',
				of: 'of',
				prevPage: 'Previous',
				nextPage: 'Next',
				zoomIn: 'Zoom in',
				zoomOut: 'Zoom out',
				download: 'Download',
				print: 'Print',
				fullscreen: 'Fullscreen',
				exitFullscreen: 'Exit fullscreen',
			},
		} );

		console.log( '[MDPV] Test viewer created:', viewer );
		return viewer;
	}
}

// Default export for compatibility
export default MDPVViewer;

// Expose globally for debugging (development only)
if ( typeof window !== 'undefined' ) {
	window.MDPVViewer = MDPVViewer;
}
