# Traklo launcher icon sources

Канон для иконки APK — **не** favicon сайта, а файлы в этой папке.

| Файл | Назначение |
| --- | --- |
| `icon.png` | Полная иконка 1024×1024 (easy mode) |
| `icon-foreground.png` | Слой foreground для adaptive icon |
| `icon-only.png` | Опционально, для справки |

## Обновить иконку в APK

1. Замените `icon.png` (и при необходимости `icon-foreground.png`) на новый макет **1024×1024**.
2. Важное содержимое держите в центральных ~66% — края обрежет adaptive icon.
3. Сгенерируйте mipmap-ресурсы:

```powershell
npm run traklo:icons
```

4. Соберите release APK:

```powershell
npm run traklo:apk:release
```

5. Увеличьте `versionCode` в `android/app/build.gradle`.

## Первичная подготовка из текущих Android-ресурсов

Если в `resources/` ещё нет PNG, скрипт сам возьмёт `android/app/src/main/res/mipmap-xxxhdpi/ic_launcher_foreground.png`:

```powershell
npm run traklo:icons:prepare
```

Фон adaptive icon: `#0F172A` (как в `values/ic_launcher_background.xml`).
