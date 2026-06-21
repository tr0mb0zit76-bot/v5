# ТЗ: коммерческий контур — порядок внедрения (2026-06)

Живой документ для сверки с roadmap. Детализация по шагам — отдельные файлы `docs/tz-step-*.md`.

**Источники:** `roadmap-2026.md`, `commercial-intelligence-roadmap.md`, решения пользователя 2026-06-21.

---

## Порядок работ

| Шаг | Направление | ТЗ | Статус |
|-----|-------------|-----|--------|
| **1** | Портрет клиента + коучинг на «Лидах» | [tz-step-01-portrait-mvp.md](./tz-step-01-portrait-mvp.md) | ✅ sprint 1 |
| **2** | Агент анализа почты | [tz-step-02-mail-agent.md](./tz-step-02-mail-agent.md) | ✅ MVP |
| **3** | HITL `contractor_insight_drafts` | [tz-step-03-insight-drafts.md](./tz-step-03-insight-drafts.md) | ✅ MVP |
| **4** | HTML-конструктор КП из лида | [tz-step-04-html-proposal-builder.md](./tz-step-04-html-proposal-builder.md) | ✅ |
| **5** | Скрипты продаж: фазы 5–6 | [tz-step-05-scripts-analytics.md](./tz-step-05-scripts-analytics.md) | ✅ |

---

## Закрыто / снято с roadmap

| Тема | Решение |
|------|---------|
| Несколько точек выгрузки в печатных формах | ✅ Решено — закрыть в `roadmap-2026.md` |
| UI «сверить хеш» в карточке документа | ❌ Не делаем (бумажная подпись); QR verify для digital PDF остаётся |
| «Сколько влезет» → печать | ⏸ Заменено шагом 4 (HTML КП) |

---

## Definition of Done (весь контур)

- Менеджер после звонка/письма фиксирует контекст → портрет и коучинг используют одни данные.
- Command bar / персона «Почта» резюмирует цепочку и предлагает черновик ответа (HITL на факты в портрет).
- КП из лида — HTML-шаблон + переменные + PDF + отправка письмом.
- Руководитель видит аналитику скриптов Play (фаза 5).

---

---

## Продолжение с другого ПК (2026-06-21)

| Действие | Команда / ссылка |
| --- | --- |
| Ветка с кодом | `git checkout feature/commercial-roadmap-steps-1-5` · `git pull` |
| SSH (Windows) | `git config --global core.sshCommand "C:/Windows/System32/OpenSSH/ssh.exe"` |
| Миграции | `php artisan migrate` (+ testing: `--env=testing`) |
| Seeder переменных КП | `php artisan db:seed --class=ProposalHtmlTemplateVariableSeeder` |
| Frontend | `npm run build` или `composer run dev` |
| Карта кода | [`docs/sync/v5-local-Components-Commercial-Roadmap.md`](./sync/v5-local-Components-Commercial-Roadmap.md) |
| Handoff для Cursor | [`docs/sync/Cursor-handoff-latest.md`](./sync/Cursor-handoff-latest.md) |
| Vault Obsidian | `pwsh -File scripts/sync-docs-to-yandex.ps1` |

**Тесты roadmap:** 21 тест в 9 файлах — см. карточку Commercial Roadmap; при общем прогоне возможны 5 падений из‑за смешения `schemaDropMany` и `RefreshDatabase` на `u_tromb`.

**Deploy checklist:** migrate → seeder → build → Gotenberg для PDF КП → smoke: merge портрета, insight draft, PDF из HTML-шаблона, страница Analytics скрипта.

---

*Обновлять статус шага в таблице после релиза каждой фазы.*
