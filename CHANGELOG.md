# Changelog

All notable changes to MaxtDesign PDF Viewer are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-05-28

### Fixed
- Shortcode-only pages now correctly enqueue the PDF viewer assets. Detection previously relied on a `the_content` filter that fires after `wp_enqueue_scripts`, so the loader script and stylesheet were never attached on pages that used `[pdf_viewer]` or `[mdpv_viewer]` without a block. Detection now reads `$post->post_content` directly during `wp_enqueue_scripts`, mirroring the pattern already used for the Gutenberg block.

### Changed
- `Tested up to:` bumped to WordPress 7.0 ("Armstrong").
- Removed `gutenberg` from `readme.txt` Tags line per WordPress.org plugin handbook guidance.
- Removed the now-orphaned `$has_shortcode` property, `detect_shortcode_usage()` method, the `the_content` filter hookup that set the flag, and the redundant `page_has_pdf_block()` helper. No behavior change beyond the fix above.

## [1.0.0] - 2025-12-29

### Added
- Initial release.
- Gutenberg block for embedding PDFs with instant preview and zero layout shift.
- `[pdf_viewer]` shortcode for classic editor and widgets.
- Server-side WebP preview generation via the ImageMagick PHP extension.
- Lazy-loaded PDF.js viewer module (`mdpv-viewer.js`) with toolbar, keyboard navigation, fullscreen, download, and print.
- Settings page (General / Server Info / Tools) with bulk processing and cache management.
- REST API endpoints under `/wp-json/mdpv/v1/`.
- Daily scheduled cache cleanup.

[1.0.1]: https://github.com/MaxtDesign/maxtdesign-pdf-viewer/releases/tag/v1.0.1
[1.0.0]: https://github.com/MaxtDesign/maxtdesign-pdf-viewer/releases/tag/v1.0.0
