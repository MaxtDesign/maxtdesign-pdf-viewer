/**
 * Copy PDF.js files from node_modules to vendor/pdfjs/
 * 
 * This script copies the necessary PDF.js files to the vendor directory
 * so they can be used by the WordPress plugin.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const PDFJS_VERSION = require( '../package.json' ).dependencies[ 'pdfjs-dist' ].replace( '^', '' );

// Source paths (node_modules)
const nodeModulesDir = path.join( __dirname, '..', 'node_modules', 'pdfjs-dist' );
const buildDir = path.join( nodeModulesDir, 'build' );

// Destination paths (vendor/pdfjs)
const vendorDir = path.join( __dirname, '..', 'vendor', 'pdfjs' );

// Files to copy
const filesToCopy = [
	{
		src: path.join( buildDir, 'pdf.min.mjs' ),
		dest: path.join( vendorDir, 'pdf.min.mjs' ),
		name: 'pdf.min.mjs'
	},
	{
		src: path.join( buildDir, 'pdf.worker.min.mjs' ),
		dest: path.join( vendorDir, 'pdf.worker.min.mjs' ),
		name: 'pdf.worker.min.mjs'
	}
];

// Directories to copy
const dirsToCopy = [
	{
		src: path.join( nodeModulesDir, 'cmaps' ),
		dest: path.join( vendorDir, 'cmaps' ),
		name: 'cmaps'
	}
];

/**
 * Ensure directory exists
 */
function ensureDir( dirPath ) {
	if ( ! fs.existsSync( dirPath ) ) {
		fs.mkdirSync( dirPath, { recursive: true } );
		console.log( `Created directory: ${ dirPath }` );
	}
}

/**
 * Copy file
 */
function copyFile( src, dest, name ) {
	try {
		if ( ! fs.existsSync( src ) ) {
			console.error( `❌ Source file not found: ${ src }` );
			return false;
		}

		fs.copyFileSync( src, dest );
		const stats = fs.statSync( dest );
		const sizeKB = ( stats.size / 1024 ).toFixed( 2 );
		console.log( `✓ Copied ${ name } (${ sizeKB } KB)` );
		return true;
	} catch ( error ) {
		console.error( `❌ Error copying ${ name }:`, error.message );
		return false;
	}
}

/**
 * Copy directory recursively
 */
function copyDir( src, dest, name ) {
	try {
		if ( ! fs.existsSync( src ) ) {
			console.error( `❌ Source directory not found: ${ src }` );
			return false;
		}

		// Ensure destination directory exists
		ensureDir( dest );

		// Get all files in source directory
		const files = fs.readdirSync( src );
		let fileCount = 0;
		let totalSize = 0;

		for ( const file of files ) {
			const srcPath = path.join( src, file );
			const destPath = path.join( dest, file );
			const stats = fs.statSync( srcPath );

			if ( stats.isDirectory() ) {
				// Recursively copy subdirectories
				const subResult = copyDir( srcPath, destPath, `${ name }/${ file }` );
				if ( subResult && subResult.success ) {
					fileCount += subResult.fileCount;
					totalSize += subResult.totalSize;
				}
			} else {
				// Copy file
				fs.copyFileSync( srcPath, destPath );
				fileCount++;
				totalSize += stats.size;
			}
		}

		const sizeKB = ( totalSize / 1024 ).toFixed( 2 );
		console.log( `✓ Copied ${ name } directory (${ fileCount } files, ${ sizeKB } KB)` );
		return { fileCount, totalSize, success: true };
	} catch ( error ) {
		console.error( `❌ Error copying ${ name } directory:`, error.message );
		return { fileCount: 0, totalSize: 0, success: false };
	}
}

/**
 * Main execution
 */
function main() {
	console.log( '📦 Copying PDF.js files to vendor/pdfjs/...' );
	console.log( `   PDF.js version: ${ PDFJS_VERSION }` );
	console.log( '' );

	// Ensure vendor/pdfjs directory exists
	ensureDir( vendorDir );

	// Create security index.php file if it doesn't exist
	const indexPhpPath = path.join( vendorDir, 'index.php' );
	if ( ! fs.existsSync( indexPhpPath ) ) {
		fs.writeFileSync( indexPhpPath, '<?php\n// Silence is golden.\n' );
		console.log( '✓ Created index.php security file' );
	}

	// Check if node_modules exists
	if ( ! fs.existsSync( nodeModulesDir ) ) {
		console.error( '❌ node_modules/pdfjs-dist not found!' );
		console.error( '   Please run: npm install' );
		process.exit( 1 );
	}

	// Copy files
	let successCount = 0;
	let failCount = 0;

	filesToCopy.forEach( ( file ) => {
		if ( copyFile( file.src, file.dest, file.name ) ) {
			successCount++;
		} else {
			failCount++;
		}
	} );

	// Copy directories
	dirsToCopy.forEach( ( dir ) => {
		const result = copyDir( dir.src, dir.dest, dir.name );
		if ( result && result.success ) {
			successCount++;
			// Create security index.php file in cmaps directory after copying
			if ( dir.name === 'cmaps' ) {
				const cmapsIndexPhpPath = path.join( dir.dest, 'index.php' );
				if ( ! fs.existsSync( cmapsIndexPhpPath ) ) {
					fs.writeFileSync( cmapsIndexPhpPath, '<?php\n// Silence is golden.\n' );
					console.log( '✓ Created index.php security file in cmaps directory' );
				}
			}
		} else {
			failCount++;
		}
	} );

	console.log( '' );
	if ( failCount === 0 ) {
		console.log( `✅ Successfully copied ${ successCount } item(s) to vendor/pdfjs/` );
	} else {
		console.error( `❌ Failed to copy ${ failCount } item(s)` );
		process.exit( 1 );
	}
}

// Run the script
main();

