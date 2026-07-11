#!/usr/bin/env python3
"""Collabis BFS crawl state manager — processes extracted page JSON and writes outputs."""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ROOT_UUID = "fa60d7c5-020b-452e-a340-41f053e6eb65"
ROOT_URL = f"https://collabis.ru/{ROOT_UUID}"
EXPORT_DIR = Path(__file__).resolve().parent.parent / "storage" / "collabis-export"
PAGES_DIR = EXPORT_DIR / "pages"
STATE_FILE = EXPORT_DIR / "crawl-state.json"
MANIFEST_FILE = EXPORT_DIR / "manifest.json"
LOG_FILE = EXPORT_DIR / "crawl-log.txt"

UUID_RE = re.compile(r"^/[0-9a-f-]{36}$", re.I)

# CRM parent mapping for top-level Collabis sections (match after stripping icon font chars)
CRM_ROOT_MAP: dict[str, str] = {
    "регламенты компании": "Регламенты работы",
    "экспресс-введении в профессию": "✈️Экспресс-введении в профессию",
    "crm": "🧮Руководство по CRM",
    "воронка продаж": "🌪️ Воронка продаж",
    "библиотека": "👨‍🎓Глоссарий",
    "шаблоны документов": "👨‍🎓Глоссарий",
}

# Collabis «Библиотека» sections that must be direct children of CRM Глоссарий
# (even when Collabis nests them under Экспресс-введение or Терминология).
GLOSSARY_DIRECT_SECTIONS: set[str] = {
    "инкотермс",
    "транспортные документы",
    "терминология",
    "финансовые документы",
    "сертификаты и декларации",
    "просто почитать",
    "типы перевозок",
    "виды транспорта",
    "шаблоны документов",
    "виды тары",
    "виды перевозок и регламентирующие их документы",
    "порты китая",
}

# Leaf template pages → hub ✒️Шаблоны документов (child of Глоссарий).
TEMPLATE_DOC_SECTIONS: set[str] = {
    "b/l",
    "invoice",
    "packing",
    "договор-заявка",
    "тн",
    "шаблон коммерческого предложения",
    "cmr",
    "чек-лист сбора данных по клиенту",
    "шаблон сбора данных по перевозке",
}

ICON_FONT_RE = re.compile(r"[\ue000-\uf8ff\u200d\ufe0f]|[\uE000-\uF8FF]")

SKIP_LINE_PATTERNS = [
    "Логистические решения",
    "Спросите Collabis AI",
    "Достигнут лимит",
    "Рабочее пространство работает только в режиме чтения",
    "Повысить тариф",
    "Создать новую страницу",
    "Обратная связь",
    "Новый чат",
    "Новый агент",
]

NAV_NOISE = {
    "Главная", "Чаты", "Входящие", "Агенты", "Команда", "Приватные", "Шаблоны",
    "Помощь", "Корзина", "C", "Представьте",  # partial matches handled below
}


def normalize_match_key(title: str) -> str:
    t = ICON_FONT_RE.sub("", title)
    t = re.sub(r"[^\w\s\u0400-\u04FF]", "", t, flags=re.UNICODE)
    return re.sub(r"\s+", " ", t).strip().lower()


def crm_parent_for(title: str) -> str | None:
    return CRM_ROOT_MAP.get(normalize_match_key(title))


def clean_content_text(raw: str, page_title: str) -> str:
    lines = raw.split("\n")
    out: list[str] = []
    started = False
    title_norm = normalize_match_key(page_title)

    for line in lines:
        s = line.strip()
        if not s:
            if started and out and out[-1] != "":
                out.append("")
            continue
        if any(p in s for p in SKIP_LINE_PATTERNS):
            continue
        if re.match(r"^Корпоративная книга продаж\s*/?", s):
            continue
        if re.match(r"^[\ue000-\uf8ff\uE000-\uF8FF\s]+$", s):
            continue
        if s in {"Главная", "Чаты", "Входящие", "Агенты", "Команда", "Приватные",
                 "Шаблоны", "Помощь", "Корзина", "C", "Новый чат", "Новый агент"}:
            continue
        if re.match(r"^[\uE000-\uF8FF\u200d\ufe0f\s/]+$", s):
            continue
        # Breadcrumb-ish single emoji + title before main content
        if not started:
            key = normalize_match_key(s)
            if key == title_norm or key.endswith(title_norm) or title_norm.endswith(key):
                started = True
                continue
            if len(s) < 80 and not any(c.islower() for c in s if c.isalpha()):
                continue
        started = True
        out.append(s)

    text = "\n".join(out).strip()
    # Remove duplicate title at start
    first_lines = text.split("\n", 2)
    if first_lines and normalize_match_key(first_lines[0]) == title_norm:
        text = "\n".join(first_lines[1:]).strip()
    return text


def to_markdown(title: str, body: str) -> str:
    paragraphs = [p.strip() for p in re.split(r"\n{2,}", body) if p.strip()]
    if not paragraphs:
        paragraphs = [ln.strip() for ln in body.split("\n") if ln.strip()]
    body_md = "\n\n".join(paragraphs)
    return f"# {title}\n\n{body_md}\n"


def load_state() -> dict:
    if STATE_FILE.exists():
        return json.loads(STATE_FILE.read_text(encoding="utf-8"))
    return {
        "queue": [f"/{ROOT_UUID}"],
        "visited": [],
        "pages": {},
        "parents": {},
        "errors": [],
    }


def save_state(state: dict) -> None:
    EXPORT_DIR.mkdir(parents=True, exist_ok=True)
    PAGES_DIR.mkdir(parents=True, exist_ok=True)
    STATE_FILE.write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding="utf-8")


def uuid_from_path(path: str) -> str:
    return path.strip("/")


def process_page(state: dict, path: str, title: str, links: list[str], content_text: str) -> None:
    uid = uuid_from_path(path)
    if uid in state["pages"]:
        return

    cleaned = clean_content_text(content_text, title)
    md = to_markdown(title, cleaned)
    md_path = PAGES_DIR / f"{uid}.md"
    md_path.write_text(md, encoding="utf-8")

    state["pages"][uid] = {
        "title": title,
        "path": path,
        "url": f"https://collabis.ru{path}",
        "markdown_path": f"storage/collabis-export/pages/{uid}.md",
        "links": links,
    }


def add_children_to_queue(state: dict, parent_path: str, links: list[str]) -> None:
    parent_uid = uuid_from_path(parent_path)
    parent_title = state["pages"].get(parent_uid, {}).get("title", "")

    for link in links:
        if not UUID_RE.match(link):
            continue
        child_uid = uuid_from_path(link)
        if child_uid not in state["parents"] and child_uid != parent_uid:
            state["parents"][child_uid] = parent_path
        if link not in state["visited"] and link not in state["queue"]:
            state["queue"].append(link)


def resolve_crm_parent_title(state: dict, uid: str) -> str:
    if uid == ROOT_UUID:
        return ""

    page = state["pages"].get(uid, {})
    title = page.get("title", "")

    title_key = normalize_match_key(title)
    if title_key in GLOSSARY_DIRECT_SECTIONS:
        return "👨‍🎓Глоссарий"
    if title_key in TEMPLATE_DOC_SECTIONS:
        return "✒️Шаблоны документов"

    # Walk up parent chain to find CRM-mapped ancestor
    current = uid
    chain: list[tuple[str, str]] = []
    seen = set()
    while current and current not in seen:
        seen.add(current)
        p = state["pages"].get(current, {})
        chain.append((current, p.get("title", "")))
        parent_path = state["parents"].get(current)
        if not parent_path:
            break
        current = uuid_from_path(parent_path)

    # Direct child of root -> use CRM root map
    parent_path = state["parents"].get(uid)
    if parent_path and uuid_from_path(parent_path) == ROOT_UUID:
        mapped = crm_parent_for(title)
        if mapped:
            return mapped
        # Section titles at root level
        for _, t in chain:
            mapped = crm_parent_for(t)
            if mapped:
                return mapped

    # Nested: immediate Collabis parent title, CRM-mapped if parent is a root section
    if parent_path:
        parent_uid = uuid_from_path(parent_path)
        parent_title = state["pages"].get(parent_uid, {}).get("title", "")
        if parent_uid == ROOT_UUID:
            mapped = crm_parent_for(title)
            return mapped or ""
        # If immediate parent is a known root section, map it
        mapped_parent = crm_parent_for(parent_title)
        if mapped_parent:
            return mapped_parent
        return parent_title

    mapped = crm_parent_for(title)
    return mapped or ""


def build_manifest(state: dict) -> list[dict]:
    manifest: list[dict] = []
    for uid, page in sorted(state["pages"].items(), key=lambda x: x[1].get("title", "")):
        if uid == ROOT_UUID:
            continue
        parent_title = resolve_crm_parent_title(state, uid)
        manifest.append({
            "parent_title": parent_title,
            "title": page["title"],
            "markdown_path": page["markdown_path"],
            "collabis_url": page["url"],
        })
    return manifest


def write_outputs(state: dict) -> None:
    manifest = build_manifest(state)
    MANIFEST_FILE.write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")

    log_lines = [
        f"Root URL: {ROOT_URL}",
        f"Pages crawled (incl. root): {len(state['pages'])}",
        f"Manifest entries (excl. root): {len(manifest)}",
        f"Visited URLs: {len(state['visited'])}",
        f"Errors: {len(state['errors'])}",
    ]
    if state["errors"]:
        log_lines.append("Error details:")
        log_lines.extend(f"  - {e}" for e in state["errors"])
    LOG_FILE.write_text("\n".join(log_lines) + "\n", encoding="utf-8")


def cmd_next(state: dict) -> None:
    while state["queue"]:
        path = state["queue"].pop(0)
        if path in state["visited"]:
            continue
        print(json.dumps({"action": "crawl", "path": path, "url": f"https://collabis.ru{path}"}))
        return
    print(json.dumps({"action": "done", "remaining": 0}))


def cmd_save(state: dict, payload: dict) -> None:
    path = payload["path"]
    uid = uuid_from_path(path)

    if payload.get("error"):
        state["errors"].append(f"{path}: {payload['error']}")
        state["visited"].append(path)
        save_state(state)
        return

    title = payload.get("title") or "Untitled"
    links = payload.get("links") or []
    content = payload.get("contentText") or ""

    process_page(state, path, title, links, content)
    state["visited"].append(path)
    add_children_to_queue(state, path, links)
    save_state(state)


def main() -> None:
    state = load_state()
    PAGES_DIR.mkdir(parents=True, exist_ok=True)

    if len(sys.argv) < 2:
        cmd_next(state)
        return

    cmd = sys.argv[1]
    if cmd == "next":
        cmd_next(state)
    elif cmd == "save":
        payload = json.loads(sys.argv[2])
        cmd_save(state, payload)
        save_state(state)
    elif cmd == "finalize":
        write_outputs(state)
        print(json.dumps({"manifest_count": len(build_manifest(state)), "pages": len(state["pages"])}))
    elif cmd == "reset":
        if STATE_FILE.exists():
            STATE_FILE.unlink()
        print("reset ok")
    elif cmd == "status":
        print(json.dumps({
            "queue": len(state["queue"]),
            "visited": len(state["visited"]),
            "pages": len(state["pages"]),
            "errors": len(state["errors"]),
        }))
    else:
        print(f"Unknown command: {cmd}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
