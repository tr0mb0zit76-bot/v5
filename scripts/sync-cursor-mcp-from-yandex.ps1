# Синхронизация локального MCP: bearer Obsidian MCP Connector из vault на Yandex Disk.
# Не трогает v5-crm-prod (токен Sanctum — только на машине, где его выпускали).
#
# Usage: pwsh -File scripts/sync-cursor-mcp-from-yandex.ps1

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$yandexExchange = Join-Path $env:USERPROFILE 'Yandex.Disk\Exchange'
$obsidianDataJson = Join-Path $yandexExchange '.obsidian\plugins\mcp-tools-istefox\data.json'
$hivemindConfigSrc = Join-Path $yandexExchange 'config.json'
$globalMcpPath = Join-Path $env:USERPROFILE '.cursor\mcp.json'
$localMcpPath = Join-Path $repoRoot '.cursor\mcp.local.json'
$hivemindLocalPath = Join-Path $repoRoot '.cursor\hivemind.config.json'

if (-not (Test-Path $yandexExchange)) {
    Write-Error "Yandex Disk vault not found: $yandexExchange"
}

if (-not (Test-Path $obsidianDataJson)) {
    Write-Error "Obsidian MCP plugin data.json not found: $obsidianDataJson"
}

$obsidianData = Get-Content -Raw -Encoding UTF8 $obsidianDataJson | ConvertFrom-Json
$bearer = $obsidianData.mcpTransport.bearerToken

if ([string]::IsNullOrWhiteSpace($bearer)) {
    Write-Error 'bearerToken is empty in Obsidian MCP Connector data.json'
}

$exchangeObsidianServer = @{
    url     = 'http://127.0.0.1:27200/mcp'
    headers = @{
        Authorization = "Bearer $bearer"
    }
}

function Merge-McpServers {
    param(
        [string]$Path,
        [hashtable]$NewServers
    )

    $config = @{ mcpServers = @{} }

    if (Test-Path $Path) {
        $raw = Get-Content -Raw -Encoding UTF8 $Path | ConvertFrom-Json
        if ($null -ne $raw.mcpServers) {
            $raw.mcpServers.PSObject.Properties | ForEach-Object {
                $config.mcpServers[$_.Name] = $_.Value
            }
        }
    }

    foreach ($key in $NewServers.Keys) {
        $config.mcpServers[$key] = $NewServers[$key]
    }

    $json = $config | ConvertTo-Json -Depth 10
    $dir = Split-Path -Parent $Path
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }

    Set-Content -Path $Path -Value $json -Encoding UTF8
}

Merge-McpServers -Path $globalMcpPath -NewServers @{ 'exchange-obsidian' = $exchangeObsidianServer }
Merge-McpServers -Path $localMcpPath -NewServers @{ 'exchange-obsidian' = $exchangeObsidianServer }

if (Test-Path $hivemindConfigSrc) {
    $hivemind = Get-Content -Raw -Encoding UTF8 $hivemindConfigSrc | ConvertFrom-Json
    if ($null -ne $hivemind.vault) {
        $hivemind.vault.path = ($yandexExchange -replace '\\', '/')
    }

    $hivemind | ConvertTo-Json -Depth 10 | Set-Content -Path $hivemindLocalPath -Encoding UTF8
}

Write-Host "Synced exchange-obsidian -> $globalMcpPath"
Write-Host "Synced exchange-obsidian -> $localMcpPath"
if (Test-Path $hivemindLocalPath) {
    Write-Host "Synced hivemind config -> $hivemindLocalPath"
}

Write-Host ''
Write-Host 'Obsidian: open vault Exchange on Yandex Disk and keep MCP Connector running (port 27200).'
Write-Host 'Prod CRM: add v5-crm-prod manually from .cursor/mcp.json.example (Sanctum token).'
