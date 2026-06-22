# SSH на прод-сервер CRM

> Канон в git: `docs/sync/prod-ssh.md` · скрипт: `scripts/prod-plink.ps1`

## Параметры (не путать с другими IP)

| Параметр | Значение |
| --- | --- |
| **IP** | `91.229.11.16` |
| **Пользователь** | `root` (по умолчанию в скрипте) |
| **Ключ (Windows)** | `C:\,ssh\private_key.ppk` |
| **Формат ключа** | PuTTY **PPK** (не OpenSSH `.pem`) |
| **Клиент** | PuTTY / `plink.exe` / Pageant |
| **Сайт CRM** | `https://crm.avtoaliyans.ru` |
| **Путь приложения на сервере** | `/var/www/www-root/data/www/avtoaliyans.ru` |

**Важно:** старый IP `109.61.108.18` — **не** прод CRM; не использовать.

## Подключение вручную (PuTTY)

1. Host: `91.229.11.16`, Port: `22`, Connection type: SSH.
2. Connection → Data → Auto-login username: `root`.
3. Connection → SSH → Auth → Credentials → Private key file: `C:\,ssh\private_key.ppk`.
4. Save session как «CRM prod».
5. Если ключ с passphrase — ввести при первом подключении или загрузить ключ в **Pageant** (tray).

## Из PowerShell (репозиторий)

```powershell
cd C:\OSPanel\home\v5.local
.\scripts\prod-plink.ps1 "whoami"
.\scripts\prod-plink.ps1 "cd /var/www/www-root/data/www/avtoaliyans.ru && git log -1 --oneline"
.\scripts\prod-plink.ps1 "tail -80 /var/www/www-root/data/www/avtoaliyans.ru/storage/logs/laravel.log"
```

Другой пользователь:

```powershell
.\scripts\prod-plink.ps1 -User www-root "whoami"
```

## Pageant (ключ с парольной фразой)

1. Запустить **Pageant** → **Add Key** → `C:\,ssh\private_key.ppk` → ввести passphrase.
2. После этого `plink` / `prod-plink.ps1` работают без повторного ввода passphrase в сессии.

## Экспорт публичного ключа

На этой машине `puttygen -O` может не поддерживаться (старая версия PuTTY). Экспорт через GUI:

**PuTTYgen** → Load → `private_key.ppk` → **Save public key** или копировать из текстового поля.

## GitHub vs прод

- **GitHub:** OpenSSH-ключ в `C:\Users\<вы>\.ssh\id_ed25519` (см. handoff).
- **Прод-сервер:** отдельный PPK в `C:\,ssh\` — это **другой** ключ и другое назначение.
