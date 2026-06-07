# AI Agent Personas (command bar)

Каталог персон ассистента: display name в UI, slug в API и audit.

## Имена

| Slug | UI | Назначение |
|------|-----|------------|
| `jarvis` | Джарвис | Глобальный ассистент |
| `galya` | Галя | Коммерция: лиды, заказы, Книга, тренажёр |
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

Obsidian: `Exchange/CRM/Roadmap/Design/AI Agent Personas.md`
