---
name: windows-powershell
description: Windows PowerShell environment configuration. Always use PowerShell commands, never bash/Linux syntax.
---

# Windows PowerShell Environment

## Critical: Operating System & Shell

**I am running on Windows 10/11 with PowerShell as the default terminal.**

## Shell Commands - ALWAYS use PowerShell syntax:

### File Operations

- List files: `Get-ChildItem` or `dir` or `ls` (PowerShell aliases work, but prefer `Get-ChildItem`)
- List only directories: `Get-ChildItem -Directory`
- List only files: `Get-ChildItem -File`
- Recursive listing: `Get-ChildItem -Recurse`

### Path Format

- Use backslashes or forward slashes (PowerShell accepts both)
- Drive letters: `C:\path\to\file`
- Never assume Linux paths like `/home/user` or `/var/log`

### Common Commands Mapping

| Linux/bash           | PowerShell                                  |
| -------------------- | ------------------------------------------- |
| `ls -la`             | `Get-ChildItem -Force`                      |
| `pwd`                | `Get-Location` or `pwd`                     |
| `cd /path`           | `Set-Location C:\path`                      |
| `cat file.txt`       | `Get-Content file.txt`                      |
| `rm file.txt`        | `Remove-Item file.txt`                      |
| `mkdir folder`       | `New-Item -ItemType Directory -Path folder` |
| `grep pattern`       | `Select-String -Pattern pattern`            |
| `ps aux`             | `Get-Process`                               |
| `echo "text" > file` | `"text"                                     |

### Environment Variables

- Access: `$env:VARIABLE_NAME`
- Set: `$env:VARIABLE_NAME = "value"`
- Example: `$env:PATH` not `$PATH`

### PowerShell Version Requirement

- **PowerShell 7 or higher is required for Cline shell integration** [citation:8]
- Check version: `$PSVersionTable.PSVersion`
- If version is below 7, prompt user to upgrade

### Execution Policy

- If scripts fail to run, execution policy may be restricted [citation:7]
- Solution: `Set-ExecutionPolicy RemoteSigned -Scope CurrentUser`
- Or run with bypass: `powershell -ExecutionPolicy Bypass -File script.ps1`

## Laravel & PHP Commands (PowerShell syntax)

```powershell
php artisan serve
php artisan make:model User
php artisan migrate
./vendor/bin/phpunit
composer install
