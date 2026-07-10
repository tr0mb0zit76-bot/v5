#Requires -Version 5.1
<#
.SYNOPSIS
    Протокол Аудит — детерминированные проверки CRM v5 (механическая часть).

.DESCRIPTION
    Запускается агентом Cursor по skill audit-protocol или вручную.
    Собирает факты: зависимости, grep-паттерны безопасности, размеры файлов,
    конфиг, git-состояние. Не меняет код.

.PARAMETER OutputPath
    Путь к markdown-отчёту. По умолчанию: docs/audit-reports/<timestamp>-mechanical.md

.PARAMETER RunTests
    Запустить php artisan test --compact (может занять минуты; нужен MySQL).

.PARAMETER Json
    Дополнительно вывести JSON-сводку в stdout (последняя строка).

.EXAMPLE
    pwsh -File scripts/audit-protocol.ps1
    pwsh -File scripts/audit-protocol.ps1 -RunTests
#>
[CmdletBinding()]
param(
    [string] $OutputPath = '',
    [switch] $RunTests,
    [switch] $Json
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Continue'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location $repoRoot

$timestamp = Get-Date -Format 'yyyy-MM-dd-HHmm'
if ([string]::IsNullOrWhiteSpace($OutputPath)) {
    $reportsDir = Join-Path $repoRoot 'docs/audit-reports'
    New-Item -ItemType Directory -Force -Path $reportsDir | Out-Null
    $OutputPath = Join-Path $reportsDir "$timestamp-mechanical.md"
}

$findings = [System.Collections.Generic.List[object]]::new()

function Add-Finding {
    param(
        [ValidateSet('critical', 'warning', 'info', 'ok')]
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
    try {
        & $Block 2>&1
    } catch {
        $_
    }
}

function Count-RgMatches {
    param(
        [string] $Pattern,
        [string[]] $Globs = @('*.php', '*.vue', '*.js', '*.ts')
    )
    $total = 0
    $files = @()
    foreach ($glob in $Globs) {
        $hits = Invoke-Quiet {
            rg --no-heading --line-number --glob $glob $Pattern $repoRoot 2>$null
        }
        if ($hits) {
            $lines = @($hits | Where-Object { $_ -is [string] -and $_.Trim() -ne '' })
            $total += $lines.Count
            foreach ($line in $lines) {
                $filePath = ($line -replace ':\d+:.*$', '')
                if ($filePath) {
                    $files += $filePath
                }
            }
        }
    }
    [ordered]@{
        count = $total
        files = ($files | Select-Object -Unique)
    }
}

# --- Git ---
$gitBranch = Invoke-Quiet { git rev-parse --abbrev-ref HEAD 2>$null }
$gitHead = Invoke-Quiet { git rev-parse --short HEAD 2>$null }
$gitDirty = Invoke-Quiet { git status --porcelain 2>$null }
$dirtyCount = if ($gitDirty) { @($gitDirty | Where-Object { $_ }).Count } else { 0 }
if ($dirtyCount -gt 0) {
    Add-Finding -Severity 'info' -Category 'git' -Message "Рабочее дерево не чистое ($dirtyCount файлов)" -Evidence ($gitDirty | Select-Object -First 8) -join "`n"
} else {
    Add-Finding -Severity 'ok' -Category 'git' -Message 'Рабочее дерево чистое'
}

# --- Composer audit ---
$composerAudit = Invoke-Quiet { composer audit --format=json 2>&1 }
$composerVulns = 0
if ($composerAudit -match '"advisories"') {
    try {
        $parsed = $composerAudit | Out-String | ConvertFrom-Json
        if ($parsed.advisories) {
            $composerVulns = ($parsed.advisories.PSObject.Properties | Measure-Object).Count
        }
    } catch {
        Add-Finding -Severity 'warning' -Category 'dependencies' -Message 'composer audit: не удалось разобрать JSON' -Evidence ($composerAudit | Select-Object -Last 3 | Out-String)
    }
}
if ($composerVulns -gt 0) {
    Add-Finding -Severity 'critical' -Category 'dependencies' -Message "composer audit: $composerVulns уязвимых пакетов" -Evidence 'composer audit'
} else {
    Add-Finding -Severity 'ok' -Category 'dependencies' -Message 'composer audit: критичных advisories не найдено (или audit недоступен)'
}

# --- NPM audit (production) ---
$npmAudit = Invoke-Quiet { npm audit --omit=dev --json 2>&1 }
$npmVulns = 0
if ($npmAudit) {
    try {
        $npmParsed = ($npmAudit | Out-String) | ConvertFrom-Json
        if ($npmParsed.metadata.vulnerabilities) {
            $v = $npmParsed.metadata.vulnerabilities
            $npmVulns = [int]$v.critical + [int]$v.high + [int]$v.moderate + [int]$v.low
        }
    } catch {
        Add-Finding -Severity 'info' -Category 'dependencies' -Message 'npm audit: пропущен или не JSON'
    }
}
if ($npmVulns -gt 0) {
    Add-Finding -Severity 'warning' -Category 'dependencies' -Message "npm audit (prod): $npmVulns записей" -Evidence 'npm audit --omit=dev'
} else {
    Add-Finding -Severity 'ok' -Category 'dependencies' -Message 'npm audit (prod): без находок или пропущен'
}

# --- Security grep patterns (из audit card CRM v5) ---
$scopeHits = Count-RgMatches -Pattern 'where\(''manager_id'',\s*\$user->id\)|manager_id\s*===\s*\$user->id' -Globs @('*.php')
if ($scopeHits.count -gt 0) {
    Add-Finding -Severity 'warning' -Category 'authorization' -Message "Прямой scope manager_id: $($scopeHits.count) вхождений (проверить OrderViewAuthorization)" -Evidence ($scopeHits.files | Select-Object -First 12) -join ', '
}

$idorHits = Count-RgMatches -Pattern "scope\s*!==\s*'all'" -Globs @('*.php')
if ($idorHits.count -gt 0) {
    Add-Finding -Severity 'info' -Category 'authorization' -Message "Проверки scope !== 'all': $($idorHits.count) — сверить с department scope" -Evidence ($idorHits.files | Select-Object -First 10) -join ', '
}

$vhtmlHits = Count-RgMatches -Pattern 'v-html' -Globs @('*.vue')
if ($vhtmlHits.count -gt 0) {
    Add-Finding -Severity 'warning' -Category 'xss' -Message "v-html в Vue: $($vhtmlHits.count) — нужен DOMPurify или escape" -Evidence ($vhtmlHits.files | Select-Object -First 15) -join ', '
}

$rawSqlHits = Count-RgMatches -Pattern 'DB::raw\(|whereRaw\(|selectRaw\(' -Globs @('*.php')
if ($rawSqlHits.count -gt 0) {
    Add-Finding -Severity 'info' -Category 'sql' -Message "Raw SQL конструкции: $($rawSqlHits.count) — проверить параметризацию" -Evidence ($rawSqlHits.files | Select-Object -First 10) -join ', '
}

# --- Large files (maintainability) ---
$largeFiles = @(
    'resources/js/Pages/Orders/Wizard.vue',
    'app/Http/Controllers/OrderWizardController.php'
)
foreach ($rel in $largeFiles) {
    $full = Join-Path $repoRoot $rel
    if (Test-Path $full) {
        $lines = (Get-Content $full | Measure-Object -Line).Lines
        if ($lines -gt 3000) {
            Add-Finding -Severity 'warning' -Category 'maintainability' -Message "$rel — $lines строк (кандидат на декомпозицию)" -Evidence $rel
        } elseif ($lines -gt 1500) {
            Add-Finding -Severity 'info' -Category 'maintainability' -Message "$rel — $lines строк"
        }
    }
}

# --- Laravel config sanity ---
$sanctumExp = Invoke-Quiet { php artisan config:show sanctum.expiration 2>&1 }
if ($sanctumExp -match 'null') {
    Add-Finding -Severity 'warning' -Category 'security' -Message 'sanctum.expiration = null — MCP/API токены без глобального TTL' -Evidence ($sanctumExp | Out-String)
}

$routeList = Invoke-Quiet { php artisan route:list --except-vendor 2>&1 }
if ($LASTEXITCODE -ne 0) {
    Add-Finding -Severity 'critical' -Category 'laravel' -Message 'php artisan route:list завершился с ошибкой' -Evidence ($routeList | Select-Object -Last 5 | Out-String)
} else {
    Add-Finding -Severity 'ok' -Category 'laravel' -Message 'route:list OK'
}

# --- Pint (style, dirty only) ---
$pint = Invoke-Quiet { vendor/bin/pint --dirty --test --format agent 2>&1 }
if ($pint -match 'FAIL|issues') {
    Add-Finding -Severity 'info' -Category 'style' -Message 'Pint: есть неотформатированные dirty PHP файлы' -Evidence ($pint | Select-Object -Last 3 | Out-String)
}

# --- Optional tests ---
$testSummary = 'skipped'
if ($RunTests) {
    $testOut = Invoke-Quiet { php artisan test --compact 2>&1 }
    $testSummary = ($testOut | Select-Object -Last 3 | Out-String).Trim()
    if ($LASTEXITCODE -ne 0) {
        Add-Finding -Severity 'critical' -Category 'tests' -Message 'PHPUnit: есть падения' -Evidence $testSummary
    } else {
        Add-Finding -Severity 'ok' -Category 'tests' -Message 'PHPUnit: все тесты прошли' -Evidence $testSummary
    }
}

# --- Prior audit card ---
$auditCard = Join-Path $repoRoot 'docs/sync/v5-local-Components-Code-Audit-2026-07.md'
$auditCardExists = Test-Path $auditCard

# --- Build markdown ---
$md = @"
# Протокол Аудит — механическая часть

**Дата:** $(Get-Date -Format 'yyyy-MM-dd HH:mm')
**Репозиторий:** $repoRoot
**Ветка:** $gitBranch @ $gitHead
**Dirty files:** $dirtyCount

## Сводка

| Severity | Count |
| --- | --- |
| critical | $(@($findings | Where-Object { $_.severity -eq 'critical' }).Count) |
| warning | $(@($findings | Where-Object { $_.severity -eq 'warning' }).Count) |
| info | $(@($findings | Where-Object { $_.severity -eq 'info' }).Count) |
| ok | $(@($findings | Where-Object { $_.severity -eq 'ok' }).Count) |

## Находки

"@

foreach ($f in ($findings | Sort-Object { switch ($_.severity) { 'critical' { 0 } 'warning' { 1 } 'info' { 2 } default { 3 } } })) {
    $md += "`n### [$($f.severity.ToUpper())] $($f.category)`n"
    $md += "- $($f.message)`n"
    if ($f.evidence) {
        $evidenceText = $f.evidence
        if ($evidenceText.Length -gt 500) {
            $evidenceText = $evidenceText.Substring(0, 497) + '...'
        }
        $md += "- Evidence: $evidenceText`n"
    }
}

$md += @"

## Контекст для субагентов

- Канон домена: ``AGENTS.md``
- Backlog аудита: ``docs/sync/v5-local-Components-Code-Audit-2026-07.md``
- Handoff: ``docs/sync/Cursor-handoff-latest.md``
- Тесты: $testSummary

## Следующий шаг

Агент Cursor по skill ``audit-protocol`` запускает три read-only субагента
(архитектура, безопасность, качество) и синтезирует итоговый отчёт.
"@

$md | Set-Content -Path $OutputPath -Encoding UTF8
Write-Host "Mechanical audit report: $OutputPath"

if ($Json) {
    $summary = [ordered]@{
        timestamp   = $timestamp
        branch      = "$gitBranch"
        head        = "$gitHead"
        dirtyCount  = $dirtyCount
        outputPath  = $OutputPath
        findings    = $findings
        tests       = $testSummary
    }
    Write-Output ($summary | ConvertTo-Json -Depth 5 -Compress)
}

exit 0
