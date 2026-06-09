# Миграции на Windows / OSPanel.
# Без mysql.exe в PATH `php artisan migrate` падает на database/schema/mysql-schema.sql.
#
# Usage:
#   pwsh -File scripts/migrate.ps1
#   pwsh -File scripts/migrate.ps1 -- --seed
#   pwsh -File scripts/migrate.ps1 -- --force

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$ospanelMysqlBins = @(
    'C:\OSPanel\modules\MySQL-8.0\bin',
    'C:\OSPanel\modules\MySQL-8.4\bin',
    'C:\OSPanel\modules\database\MySQL-8.0\bin',
    'C:\OSPanel\modules\database\MySQL-8.4\bin'
)

foreach ($bin in $ospanelMysqlBins) {
    if (Test-Path (Join-Path $bin 'mysql.exe')) {
        $env:Path = "$bin;$env:Path"
        Write-Host "MySQL CLI: $bin"
        break
    }
}

Set-Location $repoRoot

$extraArgs = @()
if ($args.Count -gt 0 -and $args[0] -eq '--') {
    $extraArgs = $args[1..($args.Count - 1)]
}

if ($extraArgs.Count -gt 0) {
    & php artisan migrate @extraArgs
} else {
    & composer run migrate --no-ansi
}
