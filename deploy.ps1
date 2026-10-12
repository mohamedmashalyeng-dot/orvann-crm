# Uploads the CRM to the hosting over SSH and then checks that the live site works.
# Setup: copy deploy.settings.example.ps1 to deploy.settings.ps1 and fill it in.
#
# Usage:
#   .\deploy.ps1              Upload public/ and run the read-only checks
#   .\deploy.ps1 -WriteTest   Also register a test customer on the live site and delete it
#   .\deploy.ps1 -CheckOnly   Upload nothing, only check the live site
#
# It only ever adds or replaces files inside the CRM's own folder. It deletes nothing on
# the server, and the customer database lives outside that folder.

param(
    [switch]$WriteTest,
    [switch]$CheckOnly
)

$ErrorActionPreference = "Stop"
$ProjectRoot = $PSScriptRoot
$SettingsFile = Join-Path $ProjectRoot "deploy.settings.ps1"

if (-not (Test-Path $SettingsFile)) {
    Write-Host "Missing deploy.settings.ps1 - copy deploy.settings.example.ps1 to deploy.settings.ps1 and fill it in first." -ForegroundColor Red
    exit 1
}
. $SettingsFile

function Stop-Deploy($message) {
    Write-Host $message -ForegroundColor Red
    exit 1
}

if (-not $CheckOnly) {
    if (-not (Test-Path (Join-Path $ProjectRoot "public\app\settings.php"))) {
        Stop-Deploy "public\app\settings.php is missing: the live site cannot run without it."
    }
    if ($RemotePath -notmatch '^[A-Za-z0-9._/-]+$' -or $RemotePath.StartsWith("/") -or $RemotePath.Contains("..")) {
        Stop-Deploy "RemotePath must be a plain path relative to the hosting home folder, e.g. domains/orvann.com/public_html/crm"
    }

    # Give script and style links a version that follows their content, so the host's
    # network cannot hand a browser an old script together with a new page.
    & node (Join-Path $ProjectRoot "tools\stamp-assets.mjs") $ProjectRoot
    if ($LASTEXITCODE -ne 0) { Stop-Deploy "Could not update the asset versions." }

    # Windows' own tar keeps forward slashes and file times, which a zip made here does not.
    $Package = Join-Path $env:TEMP "crm-deploy.tar.gz"
    $Tar = Join-Path $env:SystemRoot "System32\tar.exe"
    Write-Host "Packing public/ ..."
    & $Tar -czf $Package --exclude "*.sqlite" --exclude "*.sqlite-journal" --exclude "*.log" -C (Join-Path $ProjectRoot "public") .
    if ($LASTEXITCODE -ne 0) { Stop-Deploy "Could not pack the files." }

    $Target = "$SshUser@$SshHost"
    $SshOptions = @("-i", $SshKey, "-o", "BatchMode=yes", "-o", "IdentitiesOnly=yes", "-o", "ConnectTimeout=20")

    Write-Host "Uploading to $Target ..."
    & scp @SshOptions -P $SshPort $Package "${Target}:crm-deploy.tar.gz"
    if ($LASTEXITCODE -ne 0) {
        Stop-Deploy "Upload failed. If it says 'Permission denied', the SSH key is not added in hPanel (Advanced > SSH Access)."
    }

    # Refuses to unpack anywhere that is neither empty nor already the CRM, so a wrong
    # RemotePath can never spill these files into another website.
    $Remote = 'set -e; P=' + $RemotePath + '; ' +
        'if [ ! -d $P ]; then echo REMOTE_FOLDER_MISSING; rm -f crm-deploy.tar.gz; exit 3; fi; ' +
        'if [ $(ls -A $P | wc -l) -gt 0 ] && [ ! -f $P/api/index.php ]; then echo NOT_THE_CRM_FOLDER; rm -f crm-deploy.tar.gz; exit 4; fi; ' +
        'tar -xzf crm-deploy.tar.gz -C $P; rm -f crm-deploy.tar.gz; ' +
        'find $P -type d -exec chmod 755 {} +; find $P -type f -exec chmod 644 {} +; ' +
        'echo UNPACKED_INTO $P'
    & ssh @SshOptions -p $SshPort $Target $Remote
    if ($LASTEXITCODE -eq 3) { Stop-Deploy "The folder '$RemotePath' does not exist on the server. Check RemotePath in deploy.settings.ps1." }
    if ($LASTEXITCODE -eq 4) { Stop-Deploy "The folder '$RemotePath' has other files in it and is not the CRM. Nothing was changed." }
    if ($LASTEXITCODE -ne 0) { Stop-Deploy "Unpacking on the server failed." }

    Remove-Item $Package -Force
    Write-Host "Uploaded." -ForegroundColor Green
}

Write-Host "Checking $SiteUrl ..."
$CheckArgs = @((Join-Path $ProjectRoot "tools\check-live.mjs"), $SiteUrl, $ProjectRoot)
if ($WriteTest) { $CheckArgs += "--write-test" }
& node @CheckArgs
if ($LASTEXITCODE -ne 0) { Stop-Deploy "The live site did not pass every check - see above." }
Write-Host "Live and working: $SiteUrl" -ForegroundColor Green
