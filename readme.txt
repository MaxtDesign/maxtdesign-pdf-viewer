=== MaxtDesign PDF Viewer ===
Contributors: slaacr
Donate link: https://github.com/sponsors/MaxtDesign
Tags: pdf, viewer, document, embed, gutenberg
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The fastest PDF viewer for WordPress. Sub-200ms load times, zero layout shift, and a beautiful reading experience.

== Description ==

MaxtDesign PDF Viewer is a performance-focused PDF embedding solution for WordPress. Unlike other PDF plugins that load heavy JavaScript libraries upfront, this plugin uses a smart lazy-loading approach that keeps your pages fast.

= Key Features =

* Instant Preview - Server-generated WebP preview images display immediately
* Zero Layout Shift - CSS aspect-ratio reserves exact space before content loads
* Lazy Loading - Full PDF.js viewer loads only when needed
* Gutenberg Block - Native block editor integration
* Shortcode Support - Works in classic editor and widgets
* Keyboard Navigation - Full keyboard and screen reader accessibility
* Mobile Optimized - Touch gestures for page navigation and zoom

= Performance =

* Initial page load: < 10KB JavaScript
* First paint: < 200ms
* Full viewer: Loads on-demand
* Preview images: Optimized WebP format

= How It Works =

1. Upload a PDF to your media library
2. The plugin automatically extracts the first page as a WebP preview
3. Insert the PDF using the Gutenberg block or shortcode
4. Visitors see an instant preview image
5. Clicking "View Document" loads the interactive PDF.js viewer

= Requirements =

For automatic preview generation, your server needs one of:

* ImageMagick with PDF support (recommended)
* Ghostscript

The plugin will detect available options and use the best method automatically.

= Shortcode Usage =

[pdf_viewer id="123"]

Attributes:

* id (required) - Attachment ID of the PDF
* width - CSS width value (default: 100%)
* load - When to load viewer: click, visible, immediate (default: click)
* toolbar - Show toolbar: true/false (default: true)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/maxtdesign-pdf-viewer/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → PDF Viewer to configure options
4. Check the Server Info tab to verify your server can generate previews

= From WordPress Admin =

1. Go to Plugins → Add New
2. Search for "MaxtDesign PDF Viewer"
3. Click Install Now, then Activate

== Frequently Asked Questions ==

= Why aren't previews being generated? =

Preview generation requires ImageMagick or Ghostscript on your server. Go to Settings → PDF Viewer → Server Info to check your server capabilities. Many shared hosts have ImageMagick pre-installed.

= Can I regenerate previews for existing PDFs? =

Yes! Go to Settings → PDF Viewer → Tools and click "Process Unprocessed PDFs" to generate previews for all PDFs in your media library.

= How do I change the preview quality? =

Go to Settings → PDF Viewer and change the "Preview Quality" setting. Higher quality means sharper previews but larger file sizes.

= Does this work with page builders? =

Yes! Use the shortcode [pdf_viewer id="123"] in any page builder that supports shortcodes. The Gutenberg block works natively in the WordPress editor.

= Is it accessible? =

Yes! The viewer includes full keyboard navigation, ARIA labels, screen reader announcements, and respects reduced motion preferences.

= Can visitors download or print the PDF? =

Yes, the toolbar includes download, print, and fullscreen buttons. You can disable these in the block settings or plugin options.

== Screenshots ==

1. PDF viewer with preview image and activation button
2. Full interactive viewer with toolbar
3. Gutenberg block in the editor
4. Settings page - General tab
5. Settings page - Server Info tab
6. Settings page - Tools tab

== Changelog ==

= 1.0.0 =

* Initial release
* Gutenberg block for easy PDF embedding
* Shortcode support for classic editor
* Automatic WebP preview generation
* PDF.js integration for full viewing
* Keyboard navigation and accessibility
* Admin settings page
* Bulk processing tool
* Cache management

== Upgrade Notice ==

= 1.0.0 =

Initial release of MaxtDesign PDF Viewer.

== Privacy ==

This plugin:

* Does not collect any personal data
* Does not send data to external services
* Stores preview images locally on your server
* Uses PDF.js library loaded from your own server