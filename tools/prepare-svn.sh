#!/bin/bash
#
# Stages MaxtDesign PDF Viewer for a wp.org SVN release.
#
# Runs the Gutenberg block build, then copies an explicit allow-list of
# files into svn-upload/trunk/. The allow-list is the canonical filter
# for what ships; .distignore is a secondary safety net.
#
# Usage: ./tools/prepare-svn.sh [version]
#
# Notes:
#   - vendor/pdfjs/ ships in full (pdf.min.mjs + worker + cmaps).
#   - blocks/pdf-viewer/ ships block.json, render.php, index.php, and
#     the build/ output. edit.js / index.js / index.scss do NOT ship.
#   - admin/, assets/, includes/, languages/, blocks/ each carry an
#     index.php silence file that's copied with the directory.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$ROOT_DIR"

VERSION="${1:-$(node -p "require('./package.json').version" 2>/dev/null || echo "dev")}"
OUT_DIR="svn-upload/trunk"

echo "================================================"
echo "MaxtDesign PDF Viewer — Prepare for wp.org SVN"
echo "================================================"
echo "Version: $VERSION"
echo "Output:  $OUT_DIR/"
echo ""

# Build the Gutenberg block bundle
if [ -d "node_modules" ]; then
  npm run build
else
  echo "Installing dependencies..."
  npm install
  npm run build
fi

# Verify the block build landed
if [ ! -f "blocks/pdf-viewer/build/index.js" ]; then
  echo "ERROR: blocks/pdf-viewer/build/index.js not found after build. Aborting." >&2
  exit 1
fi

# Verify PDF.js runtime assets are present
if [ ! -f "vendor/pdfjs/pdf.min.mjs" ]; then
  echo "ERROR: vendor/pdfjs/pdf.min.mjs missing. Run 'npm run install-pdfjs'." >&2
  exit 1
fi
if [ ! -f "vendor/pdfjs/pdf.worker.min.mjs" ]; then
  echo "ERROR: vendor/pdfjs/pdf.worker.min.mjs missing. Run 'npm run install-pdfjs'." >&2
  exit 1
fi

rm -rf "$OUT_DIR"
mkdir -p \
  "$OUT_DIR/includes" \
  "$OUT_DIR/admin/css" \
  "$OUT_DIR/admin/js" \
  "$OUT_DIR/admin/views" \
  "$OUT_DIR/admin/assets/css" \
  "$OUT_DIR/admin/assets/js" \
  "$OUT_DIR/assets/css" \
  "$OUT_DIR/assets/js" \
  "$OUT_DIR/assets/images" \
  "$OUT_DIR/blocks/pdf-viewer/build" \
  "$OUT_DIR/languages" \
  "$OUT_DIR/vendor"

# Root
cp maxtdesign-pdf-viewer.php readme.txt uninstall.php "$OUT_DIR/"

# includes/
cp includes/class-mdpv-block.php \
   includes/class-mdpv-cache.php \
   includes/class-mdpv-compatibility.php \
   includes/class-mdpv-extractor.php \
   includes/class-mdpv-plugin.php \
   includes/class-mdpv-renderer.php \
   includes/class-mdpv-rest-api.php \
   includes/class-mdpv-settings.php \
   includes/index.php \
   "$OUT_DIR/includes/"

# admin/
cp admin/class-mdpv-admin.php admin/index.php "$OUT_DIR/admin/"
cp admin/css/mdpv-admin.css admin/css/index.php "$OUT_DIR/admin/css/"
cp admin/js/mdpv-admin.js admin/js/index.php "$OUT_DIR/admin/js/"
cp admin/views/index.php "$OUT_DIR/admin/views/"
cp admin/assets/css/index.php "$OUT_DIR/admin/assets/css/"
cp admin/assets/js/index.php "$OUT_DIR/admin/assets/js/"

# assets/ (frontend)
cp assets/index.php "$OUT_DIR/assets/"
cp assets/css/mdpv-viewer.css assets/css/index.php "$OUT_DIR/assets/css/"
cp assets/js/mdpv-loader.js assets/js/mdpv-viewer.js assets/js/index.php "$OUT_DIR/assets/js/"
cp assets/images/index.php "$OUT_DIR/assets/images/"

# blocks/
cp blocks/index.php "$OUT_DIR/blocks/"
cp blocks/pdf-viewer/block.json \
   blocks/pdf-viewer/render.php \
   blocks/pdf-viewer/index.php \
   "$OUT_DIR/blocks/pdf-viewer/"
cp -r blocks/pdf-viewer/build/. "$OUT_DIR/blocks/pdf-viewer/build/"

# languages/
cp languages/maxtdesign-pdf-viewer.pot languages/index.php "$OUT_DIR/languages/"

# vendor/pdfjs/ — full directory ships (large, but required at runtime)
cp -r vendor/pdfjs "$OUT_DIR/vendor/pdfjs"

# Summary
FILE_COUNT=$(find "$OUT_DIR" -type f | wc -l)
echo ""
echo "Staged $FILE_COUNT files into $OUT_DIR/"
echo ""
echo "Next: sync $OUT_DIR/* into the SVN trunk checkout, svn cp trunk tags/$VERSION,"
echo "and one atomic 'svn ci -m \"Release $VERSION\" --username slaacr'."
