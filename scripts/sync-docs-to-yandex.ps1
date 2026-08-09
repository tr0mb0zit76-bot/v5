# Копирует индексы и handoff из docs/sync/ в Obsidian vault на Yandex Disk.
#
# Usage:
#   pwsh -File scripts/sync-docs-to-yandex.ps1
#   pwsh -File scripts/sync-docs-to-yandex.ps1 -ExchangeRoot "D:\YandexDisk\Exchange"

param(
    [string]$ExchangeRoot = 'C:\Sync\Yandex.Disk\Exchange'
)

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$syncDir = Join-Path $repoRoot 'docs\sync'
$crmRoot = Join-Path $ExchangeRoot 'CRM'
$componentsDir = Join-Path $crmRoot 'v5-local\Components'

if (-not (Test-Path $syncDir)) {
    Write-Error "Source folder not found: $syncDir"
}

if (-not (Test-Path $ExchangeRoot)) {
    Write-Error "Yandex Disk Exchange not found: $ExchangeRoot`nPass -ExchangeRoot if vault is elsewhere."
}

$map = @{
    'CRM-00-index.md'                              = (Join-Path $crmRoot '00-index.md')
    'Cursor-handoff-latest.md'                     = (Join-Path $crmRoot 'Cursor-handoff-latest.md')
    'cursor-agent-startup.md'                      = (Join-Path $crmRoot 'cursor-agent-startup.md')
    'mobile-crm-messenger-redesign-plan.md'        = (Join-Path $crmRoot 'mobile-crm-messenger-redesign-plan.md')
    'v5-local-00-index.md'                         = (Join-Path $crmRoot 'v5-local\00-index.md')
    'v5-local-Components-Import-Cost-Calculator.md' = (Join-Path $componentsDir 'Import Cost Calculator.md')
    'v5-local-Components-Management-Accounting.md' = (Join-Path $componentsDir 'Management Accounting.md')
    'v5-local-Components-Print-Forms-Verification.md' = (Join-Path $componentsDir 'Print Forms Verification.md')
    'v5-local-Components-Utility-Modules.md'       = (Join-Path $componentsDir 'Utility Modules.md')
    'v5-local-Components-Commercial-Roadmap.md'  = (Join-Path $componentsDir 'Commercial Roadmap.md')
    'v5-local-Components-Sales-Book.md'          = (Join-Path $componentsDir 'Sales Book.md')
    'v5-local-Components-Fleet-Own-Fleet.md'     = (Join-Path $componentsDir 'Fleet Own Fleet.md')
    'v5-local-Components-Documents-Registry.md'  = (Join-Path $componentsDir 'Documents Registry.md')
    'v5-local-Components-Code-Audit-2026-07.md' = (Join-Path $componentsDir 'Code Audit 2026-07.md')
    'v5-local-Components-Improvement-Loop.md'  = (Join-Path $componentsDir 'Improvement Loop.md')
    'v5-local-Components-Contractor-Enrichment.md' = (Join-Path $componentsDir 'Contractor Enrichment.md')
    'v5-local-Components-OneC-BP-Connector.md' = (Join-Path $componentsDir 'OneC BP Connector.md')
    'knowledge-graph-notes.md'                   = (Join-Path $crmRoot 'knowledge-graph-notes.md')
    'sql-security-hygiene.md'                    = (Join-Path $crmRoot 'sql-security-hygiene.md')
}

foreach ($dest in $map.Values) {
    $parent = Split-Path -Parent $dest
    if (-not (Test-Path $parent)) {
        New-Item -ItemType Directory -Path $parent -Force | Out-Null
    }
}

foreach ($srcName in $map.Keys) {
    $src = Join-Path $syncDir $srcName
    if (-not (Test-Path $src)) {
        Write-Warning "Skip missing source: $src"
        continue
    }

    $dest = $map[$srcName]
    Copy-Item -Path $src -Destination $dest -Force
    Write-Host "Synced $srcName -> $dest"
}

Write-Host ''
Write-Host 'Done. Open CRM/Cursor-handoff-latest.md in Obsidian or @-mention in Cursor.'
