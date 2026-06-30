param(
    [string] $OutputDir = "storage/app/deploy-artifacts",
    [switch] $DryRun
)

$ErrorActionPreference = "Stop"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")
Push-Location $root

try {
    $head = (git rev-parse --short HEAD).Trim()
    $archiveName = "crm-prod-$head.tar"
    $archivePath = Join-Path $OutputDir $archiveName

    if (-not (Test-Path $OutputDir)) {
        New-Item -ItemType Directory -Path $OutputDir | Out-Null
    }

    if ($DryRun) {
        $files = git archive --worktree-attributes --format=tar HEAD | tar -tf -
        $devMatches = $files | Where-Object {
            $_ -match '^(tests/|docs/|\.cursor/|\.agents/|\.codegraph/|scripts/(check|debug|dump|inspect|probe|try)-)'
        }

        Write-Host "Artifact preview for HEAD $head"
        Write-Host ("Files in archive: {0}" -f @($files).Count)

        if (@($devMatches).Count -gt 0) {
            Write-Host "Unexpected dev-only files:"
            $devMatches | ForEach-Object { Write-Host " - $_" }
            exit 1
        }

        Write-Host "OK: dev-only paths are excluded by .gitattributes export-ignore."
        return
    }

    git archive --worktree-attributes --format=tar --output="$archivePath" HEAD
    Write-Host "Created $archivePath"
} finally {
    Pop-Location
}
