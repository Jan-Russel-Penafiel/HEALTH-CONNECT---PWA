# PowerShell script to create a deployment-ready zip package
$projectName = "health-connect-pwa"
$zipName = "${projectName}-$(Get-Date -Format 'yyyy-MM-dd-HHmm').zip"

Write-Host "Creating deployment package: $zipName" -ForegroundColor Green

# Get all files except vendor, large files, and development files
$filesToZip = Get-ChildItem -Recurse -File | Where-Object {
    $file = $_
    $path = $file.FullName
    
    # Exclude vendor directory
    if ($path -like "*\vendor\*") { return $false }
    
    # Exclude large document files
    if ($file.Extension -in @('.pptx', '.ppt', '.pdf', '.docx', '.doc', '.xlsx', '.xls')) { return $false }
    
    # Exclude development files
    if ($path -like "*\.git\*" -or $path -like "*\.vscode\*" -or $path -like "*\.cursor\*") { return $false }
    
    # Exclude temporary and backup files
    if ($file.Extension -in @('.tmp', '.log', '.bak', '.backup', '.zip')) { return $false }
    
    return $true
}

# Calculate total size
$totalSize = ($filesToZip | Measure-Object -Property Length -Sum).Sum / 1MB

Write-Host "Files to package: $($filesToZip.Count)" -ForegroundColor Cyan
Write-Host "Total size: $([math]::Round($totalSize, 2)) MB" -ForegroundColor Cyan

# Remove existing zip if it exists
if (Test-Path $zipName) {
    Remove-Item $zipName -Force
}

# Create temporary directory structure
$tempDir = "temp_package"
if (Test-Path $tempDir) {
    Remove-Item $tempDir -Recurse -Force
}
New-Item -ItemType Directory -Path $tempDir | Out-Null

# Copy files to temp directory
foreach ($file in $filesToZip) {
    $relativePath = $file.FullName.Substring((Get-Location).Path.Length + 1)
    $destPath = Join-Path $tempDir $relativePath
    $destDir = Split-Path $destPath -Parent
    
    if (!(Test-Path $destDir)) {
        New-Item -ItemType Directory -Path $destDir -Force | Out-Null
    }
    
    Copy-Item $file.FullName -Destination $destPath
}

# Create zip from temp directory
Add-Type -AssemblyName "System.IO.Compression.FileSystem"
[System.IO.Compression.ZipFile]::CreateFromDirectory($tempDir, $zipName)

# Clean up temp directory
Remove-Item $tempDir -Recurse -Force

# Get the actual zip size
$zipSize = (Get-Item $zipName).Length / 1MB

Write-Host ""
Write-Host "Package created successfully!" -ForegroundColor Green
Write-Host "File: $zipName" -ForegroundColor White
Write-Host "Size: $([math]::Round($zipSize, 2)) MB" -ForegroundColor White
Write-Host ""
Write-Host "Note: Run 'composer install' on target server to restore dependencies" -ForegroundColor Yellow