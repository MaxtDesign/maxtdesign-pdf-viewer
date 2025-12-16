/**
 * MaxtDesign PDF Viewer - Minimal Loader
 *
 * This script is intentionally minimal (<8KB minified).
 * It handles activation triggers and lazy-loads the full viewer.
 *
 * @package MaxtDesign\PDFViewer
 * @since 1.0.0
 */

( function() {
    'use strict';

    /**
     * Configuration object from WordPress wp_localize_script
     * @type {Object}
     */
    const config = window.mdpvConfig || {};

    /**
     * CSS class names used by the loader
     * @type {Object}
     */
    const CLASSES = {
        viewer: 'mdpv-viewer',
        preview: 'mdpv-preview',
        activate: 'mdpv-activate',
        loading: 'mdpv-loading',
        loadingActive: 'mdpv-loading-active',
        active: 'mdpv-active',
        error: 'mdpv-error-container',
        errorMessage: 'mdpv-error-message',
    };

    /**
     * Data attribute names
     * @type {Object}
     */
    const DATA = {
        pdfUrl: 'pdfUrl',
        pdfId: 'pdfId',
        pdfPages: 'pdfPages',
        load: 'load',
        toolbar: 'toolbar',
        download: 'download',
        print: 'print',
        fullscreen: 'fullscreen',
    };

    /**
     * MDPV Loader Class
     * 
     * Handles initialization and activation of PDF viewers.
     */
    class MDPVLoader {
        /**
         * Create loader instance
         */
        constructor() {
            /** @type {NodeListOf<HTMLElement>} */
            this.viewers = document.querySelectorAll( '.' + CLASSES.viewer );
            
            /** @type {WeakSet<HTMLElement>} */
            this.initializedViewers = new WeakSet();
            
            /** @type {WeakSet<HTMLElement>} */
            this.activatedViewers = new WeakSet();
            
            /** @type {Object|null} */
            this.viewerModule = null;
            
            /** @type {Promise|null} */
            this.moduleLoadPromise = null;
            
            /** @type {IntersectionObserver|null} */
            this.intersectionObserver = null;

            if ( this.viewers.length > 0 ) {
                this.init();
            }
        }

        /**
         * Initialize all viewers on the page
         */
        init() {
            // Set up intersection observer for 'visible' load behavior
            this.setupIntersectionObserver();

            // Initialize each viewer
            this.viewers.forEach( ( viewer ) => {
                if ( this.initializedViewers.has( viewer ) ) {
                    return;
                }
                
                this.initializedViewers.add( viewer );
                this.initViewer( viewer );
            } );
        }

        /**
         * Initialize a single viewer
         * 
         * @param {HTMLElement} viewer - Viewer element
         */
        initViewer( viewer ) {
            const loadBehavior = viewer.dataset[ DATA.load ] || 'click';

            switch ( loadBehavior ) {
                case 'immediate':
                    // Load viewer immediately
                    this.activateViewer( viewer );
                    break;

                case 'visible':
                    // Load when viewer enters viewport
                    if ( this.intersectionObserver ) {
                        this.intersectionObserver.observe( viewer );
                    } else {
                        // Fallback: use click behavior
                        this.attachClickHandler( viewer );
                    }
                    break;

                case 'click':
                default:
                    // Load on user interaction
                    this.attachClickHandler( viewer );
                    break;
            }
        }

        /**
         * Set up Intersection Observer for viewport-based loading
         */
        setupIntersectionObserver() {
            if ( ! ( 'IntersectionObserver' in window ) ) {
                return;
            }

            this.intersectionObserver = new IntersectionObserver(
                ( entries ) => {
                    entries.forEach( ( entry ) => {
                        if ( entry.isIntersecting ) {
                            const viewer = /** @type {HTMLElement} */ ( entry.target );
                            this.intersectionObserver.unobserve( viewer );
                            this.activateViewer( viewer );
                        }
                    } );
                },
                {
                    rootMargin: '100px', // Start loading slightly before visible
                    threshold: 0.1,
                }
            );
        }

        /**
         * Attach click handler to viewer
         * 
         * @param {HTMLElement} viewer - Viewer element
         */
        attachClickHandler( viewer ) {
            // Handle activate button click
            const activateBtn = viewer.querySelector( '.' + CLASSES.activate );
            if ( activateBtn ) {
                activateBtn.addEventListener( 'click', ( event ) => {
                    event.preventDefault();
                    this.activateViewer( viewer );
                } );
            }

            // Also handle click on preview image
            const preview = viewer.querySelector( '.' + CLASSES.preview );
            if ( preview ) {
                preview.style.cursor = 'pointer';
                preview.addEventListener( 'click', () => {
                    this.activateViewer( viewer );
                } );
            }
        }

        /**
         * Activate a viewer - show loading state and load full viewer
         * 
         * @param {HTMLElement} viewer - Viewer element
         */
        async activateViewer( viewer ) {
            // Prevent double activation
            if ( this.activatedViewers.has( viewer ) ) {
                return;
            }
            this.activatedViewers.add( viewer );

            // Show loading state
            this.showLoading( viewer );

            try {
                // Load the viewer module
                const ViewerClass = await this.loadViewerModule();

                if ( ViewerClass ) {
                    // Get viewer configuration from data attributes
                    const viewerConfig = this.getViewerConfig( viewer );

                    // Initialize the full viewer
                    new ViewerClass( viewer, viewerConfig );
                } else {
                    throw new Error( config.i18n?.loadError || 'Failed to load viewer module' );
                }
            } catch ( error ) {
                console.error( '[MDPV] Viewer activation failed:', error );
                this.showError( viewer, error.message || config.i18n?.error || 'Failed to load document' );
                
                // Allow retry
                this.activatedViewers.delete( viewer );
            }
        }

        /**
         * Load the viewer module dynamically
         * 
         * @returns {Promise<Function|null>} Viewer class constructor
         */
        async loadViewerModule() {
            // Return cached module if already loaded
            if ( this.viewerModule ) {
                return this.viewerModule;
            }

            // Return existing promise if loading in progress
            if ( this.moduleLoadPromise ) {
                return this.moduleLoadPromise;
            }

            const viewerUrl = config.viewerUrl;

            if ( ! viewerUrl ) {
                console.error( '[MDPV] Viewer URL not configured' );
                return null;
            }

            // Start loading
            this.moduleLoadPromise = ( async () => {
                try {
                    // Dynamic import
                    const module = await import( viewerUrl );
                    
                    // Cache the module
                    this.viewerModule = module.MDPVViewer || module.default;
                    
                    return this.viewerModule;
                } catch ( error ) {
                    console.error( '[MDPV] Failed to load viewer module:', error );
                    this.moduleLoadPromise = null;
                    return null;
                }
            } )();

            return this.moduleLoadPromise;
        }

        /**
         * Get viewer configuration from data attributes
         * 
         * @param {HTMLElement} viewer - Viewer element
         * @returns {Object} Viewer configuration
         */
        getViewerConfig( viewer ) {
            return {
                pdfUrl: viewer.dataset[ DATA.pdfUrl ] || '',
                pdfId: parseInt( viewer.dataset[ DATA.pdfId ] || '0', 10 ),
                pageCount: parseInt( viewer.dataset[ DATA.pdfPages ] || '1', 10 ),
                showToolbar: viewer.dataset[ DATA.toolbar ] !== 'false',
                showDownload: viewer.dataset[ DATA.download ] !== 'false',
                showPrint: viewer.dataset[ DATA.print ] !== 'false',
                showFullscreen: viewer.dataset[ DATA.fullscreen ] !== 'false',
                pdfjsUrl: config.pdfjsUrl || '', // Local PDF.js URL if available
                workerUrl: config.pdfWorkerUrl || '',
                cmapsUrl: config.cmapsUrl || '', // Local cmaps URL if available
                i18n: config.i18n || {},
            };
        }

        /**
         * Show loading state
         * 
         * @param {HTMLElement} viewer - Viewer element
         */
        showLoading( viewer ) {
            viewer.classList.add( CLASSES.loadingActive );

            const loadingEl = viewer.querySelector( '.' + CLASSES.loading );
            const activateBtn = viewer.querySelector( '.' + CLASSES.activate );

            if ( loadingEl ) {
                loadingEl.hidden = false;
            }

            if ( activateBtn ) {
                activateBtn.hidden = true;
            }
        }

        /**
         * Hide loading state
         * 
         * @param {HTMLElement} viewer - Viewer element
         */
        hideLoading( viewer ) {
            viewer.classList.remove( CLASSES.loadingActive );

            const loadingEl = viewer.querySelector( '.' + CLASSES.loading );
            if ( loadingEl ) {
                loadingEl.hidden = true;
            }
        }

        /**
         * Show error message
         * 
         * @param {HTMLElement} viewer - Viewer element
         * @param {string} message - Error message
         */
        showError( viewer, message ) {
            this.hideLoading( viewer );

            // Show activate button again for retry
            const activateBtn = viewer.querySelector( '.' + CLASSES.activate );
            if ( activateBtn ) {
                activateBtn.hidden = false;
            }

            // Show error container
            let errorContainer = viewer.querySelector( '.' + CLASSES.error );
            
            if ( errorContainer ) {
                errorContainer.innerHTML = `<div class="${CLASSES.errorMessage}">${this.escapeHtml( message )}</div>`;
                errorContainer.hidden = false;

                // Auto-hide error after 5 seconds
                setTimeout( () => {
                    if ( errorContainer ) {
                        errorContainer.hidden = true;
                    }
                }, 5000 );
            }
        }

        /**
         * Escape HTML special characters
         * 
         * @param {string} text - Text to escape
         * @returns {string} Escaped text
         */
        escapeHtml( text ) {
            const div = document.createElement( 'div' );
            div.textContent = text;
            return div.innerHTML;
        }
    }

    /**
     * Initialize loader when DOM is ready
     */
    function initLoader() {
        new MDPVLoader();
    }

    // Initialize based on document state
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initLoader );
    } else {
        initLoader();
    }

    // Expose for external use if needed
    window.MDPVLoader = MDPVLoader;

} )();

