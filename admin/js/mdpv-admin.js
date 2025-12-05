/**
 * MaxtDesign PDF Viewer - Admin Scripts
 *
 * @package MaxtDesign\PDFViewer
 * @since 1.0.0
 */

/* global jQuery, mdpvAdmin */

( function( $ ) {
    'use strict';

    /**
     * Admin handler object
     */
    const MDPVAdmin = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $( '#mdpv-bulk-process' ).on( 'click', this.bulkProcess.bind( this ) );
            $( '#mdpv-clear-cache' ).on( 'click', this.clearCache.bind( this ) );
            $( '#mdpv-refresh-capabilities' ).on( 'click', this.refreshCapabilities.bind( this ) );
        },

        /**
         * Show spinner
         *
         * @param {jQuery} $button Button element
         */
        showSpinner: function( $button ) {
            $button.prop( 'disabled', true );
            $button.siblings( '.spinner' ).addClass( 'is-active' );
        },

        /**
         * Hide spinner
         *
         * @param {jQuery} $button Button element
         */
        hideSpinner: function( $button ) {
            $button.prop( 'disabled', false );
            $button.siblings( '.spinner' ).removeClass( 'is-active' );
        },

        /**
         * Show message
         *
         * @param {jQuery} $container Container element
         * @param {string} message    Message text
         * @param {string} type       Message type (success, error, info)
         */
        showMessage: function( $container, message, type ) {
            const $message = $( '<div class="mdpv-message mdpv-message-' + type + '">' + message + '</div>' );
            $container.find( '.mdpv-message' ).remove();

            $container.append( $message );

            // Auto-remove after 5 seconds
            setTimeout( function() {
                $message.fadeOut( function() {
                    $( this ).remove();
                } );
            }, 5000 );
        },

        /**
         * Bulk process PDFs
         *
         * @param {Event} e Click event
         */
        bulkProcess: function( e ) {
            e.preventDefault();

            const self = this;
            const $button = $( e.currentTarget );
            const $container = $button.closest( '.mdpv-tool-card' );
            const $progress = $container.find( '.mdpv-progress-container' );
            const $progressFill = $progress.find( '.mdpv-progress-fill' );
            const $progressText = $progress.find( '.mdpv-progress-text' );

            // Get initial unprocessed count
            let totalToProcess = parseInt( $( '#mdpv-unprocessed-pdfs' ).text(), 10 );
            let processedSoFar = 0;
            let failedSoFar = 0;

            if ( totalToProcess === 0 ) {
                return;
            }

            // Show progress
            $progress.show();
            $progressFill.css( 'width', '0%' );
            $progressText.text( mdpvAdmin.i18n.processing );
            self.showSpinner( $button );

            /**
             * Process batch
             */
            function processBatch() {
                $.ajax( {
                    url: mdpvAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'mdpv_bulk_process',
                        nonce: mdpvAdmin.nonce
                    },
                    success: function( response ) {
                        if ( response.success ) {
                            processedSoFar += response.data.processed;
                            failedSoFar += response.data.failed;

                            // Update progress
                            const progress = Math.round( ( processedSoFar / totalToProcess ) * 100 );
                            $progressFill.css( 'width', progress + '%' );

                            // Update text
                            let statusText = mdpvAdmin.i18n.processed.replace( '%d', processedSoFar );
                            if ( failedSoFar > 0 ) {
                                statusText += ' (' + mdpvAdmin.i18n.failed.replace( '%d', failedSoFar ) + ')';
                            }
                            if ( response.data.remaining > 0 ) {
                                statusText += ' - ' + mdpvAdmin.i18n.remaining.replace( '%d', response.data.remaining );
                            }
                            $progressText.text( statusText );

                            // Update stats display
                            $( '#mdpv-processed-pdfs' ).text( parseInt( $( '#mdpv-processed-pdfs' ).text(), 10 ) + response.data.processed );
                            $( '#mdpv-unprocessed-pdfs' ).text( response.data.remaining );

                            // Continue if more to process
                            if ( response.data.remaining > 0 ) {
                                setTimeout( processBatch, 500 ); // Small delay between batches
                            } else {
                                // Done!
                                self.hideSpinner( $button );
                                $progressFill.css( 'width', '100%' );
                                self.showMessage( $container, mdpvAdmin.i18n.success, 'success' );

                                // Disable button if nothing left
                                $button.prop( 'disabled', true );
                            }
                        } else {
                            self.hideSpinner( $button );
                            self.showMessage( $container, response.data.message || mdpvAdmin.i18n.error, 'error' );
                        }
                    },
                    error: function() {
                        self.hideSpinner( $button );
                        self.showMessage( $container, mdpvAdmin.i18n.error, 'error' );
                    }
                } );
            }

            // Start processing
            processBatch();
        },

        /**
         * Clear cache
         *
         * @param {Event} e Click event
         */
        clearCache: function( e ) {
            e.preventDefault();

            // Confirm
            if ( ! confirm( mdpvAdmin.i18n.confirmClear ) ) {
                return;
            }

            const self = this;
            const $button = $( e.currentTarget );
            const $container = $button.closest( '.mdpv-tool-card' );

            self.showSpinner( $button );

            $.ajax( {
                url: mdpvAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mdpv_clear_cache',
                    nonce: mdpvAdmin.nonce
                },
                success: function( response ) {
                    self.hideSpinner( $button );

                    if ( response.success ) {
                        // Update stats
                        $( '#mdpv-cache-files' ).text( '0' );
                        $( '#mdpv-cache-size' ).text( '0 B' );

                        // Show message
                        let message = mdpvAdmin.i18n.cacheCleared;
                        if ( response.data.deleted > 0 ) {
                            message += ' ' + mdpvAdmin.i18n.filesDeleted.replace( '%d', response.data.deleted );
                        }
                        self.showMessage( $container, message, 'success' );
                    } else {
                        self.showMessage( $container, response.data.message || mdpvAdmin.i18n.error, 'error' );
                    }
                },
                error: function() {
                    self.hideSpinner( $button );
                    self.showMessage( $container, mdpvAdmin.i18n.error, 'error' );
                }
            } );
        },

        /**
         * Refresh capabilities
         *
         * @param {Event} e Click event
         */
        refreshCapabilities: function( e ) {
            e.preventDefault();

            // Simply reload the page to refresh transient
            const $button = $( e.currentTarget );
            this.showSpinner( $button );

            // Delete transient via quick AJAX call, then reload
            $.ajax( {
                url: mdpvAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mdpv_refresh_capabilities',
                    nonce: mdpvAdmin.nonce
                },
                complete: function() {
                    window.location.reload();
                }
            } );
        }
    };

    // Initialize on document ready
    $( document ).ready( function() {
        MDPVAdmin.init();
    } );

} )( jQuery );
