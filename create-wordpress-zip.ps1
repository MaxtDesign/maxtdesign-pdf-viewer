# PowerShell script to create WordPress.org submission zip
# Excludes development files and only includes production-ready files

$pluginName = "maxtdesign-pdf-viewer"
$version = "1.0.0"
$zipName = "${pluginName}-${version}.zip"
$tempDir = "wp-submission-temp"

# Remove old zip if exists
if (Test-Path $zipName) {
    Remove-Item $zipName -Force
}

# Remove temp directory if exists
if (Test-Path $tempDir) {
    Remove-Item $tempDir -Recurse -Force
}

# Create temp directory
New-Item -ItemType Directory -Path $tempDir | Out-Null
New-Item -ItemType Directory -Path "${tempDir}\${pluginName}" | Out-Null

# Files and directories to include
$includes = @(
    "maxtdesign-pdf-viewer.php",
    "readme.txt",
    "uninstall.php",
    "admin",
    "assets",
    "blocks",
    "includes",
    "languages",
    "vendor"
)

# Copy files and directories
foreach ($item in $includes) {
    if (Test-Path $item) {
        Copy-Item -Path $item -Destination "${tempDir}\${pluginName}\$item" -Recurse -Force
        Write-Host "Copied: $item"
    }
}

# Remove source files from blocks (keep only build output)
$blockSourceDir = "${tempDir}\${pluginName}\blocks\pdf-viewer"
if (Test-Path "${blockSourceDir}\index.scss") {
    Remove-Item "${blockSourceDir}\index.scss" -Force
    Write-Host "Removed: blocks/pdf-viewer/index.scss (source file)"
}

# Create zip file
Write-Host "`nCreating zip file: $zipName"
Compress-Archive -Path "${tempDir}\${pluginName}\*" -DestinationPath $zipName -Force

# Cleanup temp directory
Remove-Item $tempDir -Recurse -Force

Write-Host "`n✅ WordPress.org submission zip created: $zipName"
Write-Host "Files included:"
Get-ChildItem -Path .\$zipName | Select-Object Name, @{Name="Size (MB)";Expression={[math]::Round($_.Length/1MB, 2)}}

