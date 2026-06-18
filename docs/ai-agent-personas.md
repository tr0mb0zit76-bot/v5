# AI Agent Personas (command bar)

Каталог персон ассистента: display name в UI, slug в API и audit.

## Имена

| Slug | UI | Назначение |
|------|-----|------------|
| `jarvis` | Джарвис | Глобальный ассистент |
| `galya` | Галя | Коммерция: лиды, заказы, Книга, тренажёр |
| `rodion` | Родион | Руководитель продаж: команда, воронка, что подкрутить |
| `yurik` | Юрик | Юридический помощник (формы, условия) |
| `strazh` | Страж | СБ: контрагенты, scoring |

Persona **не ограничивает** MCP tools жёстко — меняет system prompt и приоритеты. Доступ к tools по RBAC пользователя.

## API

```http
POST /agent/command-bar/chat
{
  "message": "...",
  "history": [],
  "agent_slug": "galya"
}
```

## Конфиг

`config/ai_agents.php` — добавление persona: slug, display_name, prompt_lead, visibility_areas.

Лимиты истории command bar (localStorage / request / LLM, режим «Расширить память»): `config/ai.php` → `command_bar.history.tiers`, логика — `App\Support\CommandBarHistoryLimits`. Пользовательская инструкция — [ai-assistants-user-guide.md](./ai-assistants-user-guide.md).

Obsidian: `Exchange/CRM/Roadmap/Design/AI Agent Personas.md`
