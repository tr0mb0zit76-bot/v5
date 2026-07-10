#Requires -Version 5.1
<#
.SYNOPSIS
    Синхронизирует промпты rdudov/agents в agents/rdudov/ (shallow clone + copy).

.EXAMPLE
    pwsh -File scripts/sync-rdudov-agents.ps1
#>
[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$targetDir = Join-Path $repoRoot 'agents/rdudov'
$tempDir = Join-Path $env:TEMP "rdudov-agents-sync-$(Get-Date -Format 'yyyyMMddHHmmss')"

New-Item -ItemType Directory -Force -Path $targetDir | Out-Null

try {
    git clone --depth 1 https://github.com/rdudov/agents.git $tempDir | Out-Null

    $files = @(
        'LICENSE',
        'README.md',
        '00_agent_development.md',
        '01_orchestrator.md',
        '04_architect_prompt.md',
        '05_architecture_reviewer_prompt.md',
        '09_agent_code_reviewer.md'
    )

    foreach ($name in $files) {
        $src = Join-Path $tempDir $name
        if (Test-Path $src) {
            Copy-Item $src (Join-Path $targetDir $name) -Force
            Write-Host "Updated: agents/rdudov/$name"
        }
    }

    Write-Host "Done. Protocols: agents/audit/, agents/architecture/"
} finally {
    if (Test-Path $tempDir) {
        Remove-Item $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}
