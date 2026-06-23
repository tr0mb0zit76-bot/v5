# SSH на прод CRM. Документация: docs/sync/prod-ssh.md
# IP: 91.229.11.16 | Ключ PPK: C:\,ssh\private_key.ppk | User: root
# Если ключ с passphrase — загрузите в Pageant перед вызовом.
# Пример: .\scripts\prod-plink.ps1 "cd /var/www/www-root/data/www/avtoaliyans.ru && git log -1 --oneline"
param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string]$RemoteCommand,

    [string]$User = 'root'
)

$ppk = 'C:\,ssh\private_key.ppk'
$hostName = '91.229.11.16'
$plink = 'C:\Program Files\PuTTY\plink.exe'

if (-not (Test-Path $ppk)) {
    Write-Error "PPK не найден: $ppk"
    exit 1
}

if (-not (Test-Path $plink)) {
    Write-Error "plink.exe не найден: $plink"
    exit 1
}

# -batch: не зависать на host key / passphrase в неинтерактивном режиме (ключ — в Pageant).
& $plink -batch -i $ppk "${User}@${hostName}" $RemoteCommand
