#Requires -Version 5.1
<#
.SYNOPSIS
    Протокол Архитектура — автономная механическая оценка структуры CRM v5.

.DESCRIPTION
    Работает OFFLINE: только локальный репозиторий, php/composer/rg из PATH.
    Не клонирует git, не ходит в сеть.

.PARAMETER OutputPath
    Markdown-отчёт. По умолчанию docs/architecture-reports/<timestamp>-mechanical.md

.PARAMETER Json
    JSON-сводка в stdout (последняя строка).

.EXAMPLE
    pwsh -File scripts/architecture-protocol.ps1
#>
[CmdletBinding()]
param(
    [string] $OutputPath = '',
    [switch] $Json
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Continue'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location $repoRoot

$timestamp = Get-Date -Format 'yyyy-MM-dd-HHmm'
if ([string]::IsNullOrWhiteSpace($OutputPath)) {
    $reportsDir = Join-Path $repoRoot 'docs/architecture-reports'
    New-Item -ItemType Directory -Force -Path $reportsDir | Out-Null
    $OutputPath = Join-Path $reportsDir "$timestamp-mechanical.md"
}

$findings = [System.Collections.Generic.List[object]]::new()

function Add-Finding {
    param(
        [ValidateSet('critical', 'warning', 'info', 'ok', 'excellent')]
        [string] $Severity,
        [string] $Category,
        [string] $Message,
        [string] $Evidence = ''
    )
    $script:findings.Add([ordered]@{
            severity = $Severity
            category = $Category
            message  = $Message
            evidence = $Evidence
        })
}

function Invoke-Quiet {
    param([scriptblock] $Block)
    try { & $Block 2>&1 } catch { $_ }
}

function Get-RelPath {
    param([string] $FullPath)
    if ($FullPath.StartsWith($repoRoot)) {
        return $FullPath.Substring($repoRoot.Length).TrimStart('\', '/')
    }
    return $FullPath
}

function Count-RgMatches {
    param([string] $Pattern, [string[]] $Globs = @('*.php'))
    $total = 0
    $files = @()
    foreach ($glob in $Globs) {
        $hits = Invoke-Quiet { rg --no-heading --line-number --glob $glob $Pattern $repoRoot 2>$null }
        if ($hits) {
            $lines = @($hits | Where-Object { $_ -is [string] -and $_.Trim() -ne '' })
            $total += $lines.Count
            foreach ($line in $lines) {
                $fp = ($line -replace ':\d+:.*$', '')
                if ($fp) { $files += (Get-RelPath $fp) }
            }
        }
    }
    [ordered]@{ count = $total; files = ($files | Select-Object -Unique) }
}

# --- Git (local) ---
$gitBranch = Invoke-Quiet { git rev-parse --abbrev-ref HEAD 2>$null }
$gitHead = Invoke-Quiet { git rev-parse --short HEAD 2>$null }

# --- Vendor prompts (autonomy) ---
$rdudovFiles = @(
    'agents/rdudov/04_architect_prompt.md',
    'agents/rdudov/05_architecture_reviewer_prompt.md',
    'agents/rdudov/09_agent_code_reviewer.md'
)
$missingRdudov = $rdudovFiles | Where-Object { -not (Test-Path (Join-Path $repoRoot $_)) }
if ($missingRdudov) {
    Add-Finding -Severity 'warning' -Category 'autonomy' -Message 'Промпты rdudov не полностью в vendor — нужен sync-rdudov-agents.ps1 (опционально, вручную)' -Evidence ($missingRdudov -join ', ')
} else {
    Add-Finding -Severity 'ok' -Category 'autonomy' -Message 'agents/rdudov/ — vendor промпты на месте (протокол автономен)'
}

# --- Scripts: external network dependencies ---
$networkPatterns = @(
    'git clone',
    'github\.com',
    'raw\.githubusercontent',
    'Invoke-RestMethod\s+-Uri\s+["'']https?://',
    'curl\s+(-[^\s]+\s+)*https?://',
    'wget\s+https?://'
)
$optInNetworkScripts = @(
    'sync-rdudov-agents.ps1'
)
$scriptFiles = Get-ChildItem (Join-Path $repoRoot 'scripts') -File -Include *.ps1, *.php, *.sh -ErrorAction SilentlyContinue
foreach ($sf in $scriptFiles) {
    if ($optInNetworkScripts -contains $sf.Name) { continue }
    $content = Get-Content $sf.FullName -Raw -ErrorAction SilentlyContinue
    if (-not $content) { continue }
    foreach ($pat in $networkPatterns) {
        if ($content -match $pat) {
            Add-Finding -Severity 'warning' -Category 'autonomy' -Message "Скрипт $($sf.Name) — возможная внешняя зависимость (сеть)" -Evidence $pat
            break
        }
    }
}

# --- Module map: app/Services ---
$serviceRoot = Join-Path $repoRoot 'app/Services'
$serviceDomains = @{}
if (Test-Path $serviceRoot) {
    Get-ChildItem $serviceRoot -Directory | ForEach-Object {
        $count = (Get-ChildItem $_.FullName -Recurse -Filter '*.php' -File -ErrorAction SilentlyContinue | Measure-Object).Count
        $serviceDomains[$_.Name] = $count
    }
}
$serviceDomainSummary = ($serviceDomains.GetEnumerator() | Sort-Object Value -Descending | ForEach-Object { "$($_.Key):$($_.Value)" }) -join ', '

# Duplicate basenames *Service.php in different folders
$dupServices = Get-ChildItem $serviceRoot -Recurse -Filter '*Service.php' -File -ErrorAction SilentlyContinue |
    Group-Object BaseName | Where-Object { $_.Count -gt 1 }
if ($dupServices) {
    foreach ($g in $dupServices) {
        $paths = ($g.Group | ForEach-Object { Get-RelPath $_.FullName }) -join '; '
        Add-Finding -Severity 'warning' -Category 'modules' -Message "Дублирующее имя сервиса: $($g.Name) ($($g.Count)×)" -Evidence $paths
    }
} else {
    Add-Finding -Severity 'excellent' -Category 'modules' -Message 'Нет одинаковых имён *Service.php в разных папках'
}

# --- Parallel abstractions (architect review candidates) ---
$authClasses = Count-RgMatches -Pattern 'class \w+(Authorization|AccessGate|Access)\b' -Globs @('*.php')
if ($authClasses.count -gt 8) {
    Add-Finding -Severity 'info' -Category 'boundaries' -Message "Классов Authorization/Access/Gate: $($authClasses.count) — проверить единый паттерн (OrderViewAuthorization как канон)" -Evidence (($authClasses.files | Select-Object -First 12) -join ', ')
}

$catalogClasses = Count-RgMatches -Pattern 'class \w+Catalog\b' -Globs @('*.php')
if ($catalogClasses.count -gt 10) {
    Add-Finding -Severity 'info' -Category 'boundaries' -Message "Catalog-классов: $($catalogClasses.count) — норма для CRM, но сверить дубли справочников" -Evidence (($catalogClasses.files | Select-Object -First 12) -join ', ')
}

$presenterHits = Count-RgMatches -Pattern 'class \w+Presenter\b' -Globs @('*.php')
Add-Finding -Severity 'info' -Category 'patterns' -Message "Presenter-классов: $($presenterHits.count) (эталон Load Board; Wizard — контраст)" -Evidence (($presenterHits.files) -join ', ')

# --- Layer violations heuristic ---
$controllerDb = Count-RgMatches -Pattern 'DB::(table|select|insert|update|delete|raw)' -Globs @('*Controller.php')
if ($controllerDb.count -gt 0) {
    Add-Finding -Severity 'warning' -Category 'layers' -Message "DB:: в контроллерах: $($controllerDb.count) — предпочтительно Services/Repositories" -Evidence (($controllerDb.files | Select-Object -First 10) -join ', ')
}

# --- Fat files (maintainability / elegance) ---
$fatThreshold = 1500
$fatFiles = @()
$scanPaths = @(
    (Join-Path $repoRoot 'app/Http/Controllers'),
    (Join-Path $repoRoot 'app/Services'),
    (Join-Path $repoRoot 'resources/js/Pages')
)
foreach ($sp in $scanPaths) {
    if (-not (Test-Path $sp)) { continue }
    Get-ChildItem $sp -Recurse -Include *.php, *.vue -File | ForEach-Object {
        $lines = (Get-Content $_.FullName -ErrorAction SilentlyContinue | Measure-Object -Line).Lines
        if ($lines -ge $fatThreshold) {
            $fatFiles += [PSCustomObject]@{ Path = (Get-RelPath $_.FullName); Lines = $lines }
        }
    }
}
$fatFiles = $fatFiles | Sort-Object Lines -Descending | Select-Object -Unique -Property Path, Lines
foreach ($f in ($fatFiles | Select-Object -First 8)) {
    $sev = if ($f.Lines -ge 4000) { 'warning' } else { 'info' }
    Add-Finding -Severity $sev -Category 'elegance' -Message "$($f.Path) — $($f.Lines) строк (нарушает изящность/SRP)" -Evidence $f.Path
}

# --- docs/sync coverage ---
$syncComponents = @(Get-ChildItem (Join-Path $repoRoot 'docs/sync') -Filter 'v5-local-Components-*.md' -File | ForEach-Object { $_.Name })
$indexContent = Get-Content (Join-Path $repoRoot 'docs/sync/v5-local-00-index.md') -Raw -ErrorAction SilentlyContinue
$unindexed = @()
foreach ($sc in $syncComponents) {
    if ($indexContent -and $indexContent -notmatch [regex]::Escape($sc)) {
        $unindexed += $sc
    }
}
if ($unindexed.Count -gt 0) {
    Add-Finding -Severity 'info' -Category 'documentation' -Message "Карточки sync без ссылки в v5-local-00-index.md: $($unindexed.Count)" -Evidence ($unindexed -join ', ')
}

# --- Protocol skills present ---
$skills = @(
    '.cursor/skills/audit-protocol/SKILL.md',
    '.cursor/skills/architecture-protocol/SKILL.md'
)
foreach ($sk in $skills) {
    if (-not (Test-Path (Join-Path $repoRoot $sk))) {
        Add-Finding -Severity 'warning' -Category 'autonomy' -Message "Skill не найден: $sk"
    }
}

# --- Routes sanity (local php) ---
$routeList = Invoke-Quiet { php artisan route:list --except-vendor 2>&1 }
if ($LASTEXITCODE -ne 0) {
    Add-Finding -Severity 'critical' -Category 'laravel' -Message 'route:list failed' -Evidence ($routeList | Select-Object -Last 3 | Out-String)
} else {
    Add-Finding -Severity 'ok' -Category 'laravel' -Message 'route:list OK'
}

# --- Build markdown ---
$md = @"
# Протокол Архитектура — механическая часть

**Дата:** $(Get-Date -Format 'yyyy-MM-dd HH:mm')
**Репозиторий:** $repoRoot
**Ветка:** $gitBranch @ $gitHead
**Режим:** автономный (без сети)

## Домены app/Services (файлов .php)

$serviceDomainSummary

## Сводка находок

| Severity | Count |
| --- | --- |
| critical | $(@($findings | Where-Object { $_.severity -eq 'critical' }).Count) |
| warning | $(@($findings | Where-Object { $_.severity -eq 'warning' }).Count) |
| info | $(@($findings | Where-Object { $_.severity -eq 'info' }).Count) |
| ok / excellent | $(@($findings | Where-Object { $_.severity -in @('ok','excellent') }).Count) |

## Находки

"@

foreach ($f in ($findings | Sort-Object {
            switch ($_.severity) {
                'critical' { 0 }
                'warning' { 1 }
                'info' { 2 }
                'ok' { 3 }
                'excellent' { 4 }
                default { 5 }
            }
        })) {
    $md += "`n### [$($f.severity.ToUpper())] $($f.category)`n- $($f.message)`n"
    if ($f.evidence) {
        $ev = $f.evidence
        if ($ev.Length -gt 600) { $ev = $ev.Substring(0, 597) + '...' }
        $md += "- Evidence: $ev`n"
    }
}

$md += @"

## Контекст для субагентов

- Канон домена: ``AGENTS.md``
- Карта компонентов: ``docs/sync/v5-local-00-index.md``
- Эталон изящности: Load Board (Presenter/Advisor), rdudov ``04`` + ``05``
- Протокол: ``agents/architecture/00_orchestrator.md``

## Следующий шаг

Skill ``architecture-protocol`` → субагенты rdudov 04/05 + module topology + autonomy.
"@

$md | Set-Content -Path $OutputPath -Encoding UTF8
Write-Host "Architecture mechanical report: $OutputPath"

if ($Json) {
    Write-Output (@{
            timestamp        = $timestamp
            branch           = "$gitBranch"
            head             = "$gitHead"
            outputPath       = $OutputPath
            serviceDomains   = $serviceDomains
            findings         = $findings
        } | ConvertTo-Json -Depth 5 -Compress)
}

exit 0
