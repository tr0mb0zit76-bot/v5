#!/usr/bin/env python3
"""BFS state + markdown/manifest helpers for Collabis crawl."""

from __future__ import annotations

import importlib.util
import json
import sys
from pathlib import Path

ROOT_UUID = "fa60d7c5-020b-452e-a340-41f053e6eb65"
EXPORT = Path(__file__).resolve().parent.parent / "storage" / "collabis-export"
STATE_FILE = EXPORT / "crawl-state.json"

_spec = importlib.util.spec_from_file_location(
    "collabis_bfs_crawl", Path(__file__).resolve().parent / "collabis-bfs-crawl.py"
)
_mod = importlib.util.module_from_spec(_spec)
assert _spec and _spec.loader
_spec.loader.exec_module(_mod)


def load() -> dict:
    if STATE_FILE.exists():
        return json.loads(STATE_FILE.read_text(encoding="utf-8"))
    return {
        "queue": [f"/{ROOT_UUID}"],
        "visited": [],
        "pages": {},
        "parents": {},
        "errors": [],
    }


def save(state: dict) -> None:
    EXPORT.mkdir(parents=True, exist_ok=True)
    STATE_FILE.write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding="utf-8")


def next_url(state: dict) -> str | None:
    while state["queue"]:
        path = state["queue"].pop(0)
        if path in state["visited"]:
            continue
        return path
    return None


def ingest(state: dict, path: str, title: str, links: list[str], content_text: str) -> dict:
    uid = path.strip("/")
    _mod.process_page(state, path, title, links, content_text)
    state["visited"].append(path)
    _mod.add_children_to_queue(state, path, links)
    save(state)
    cleaned = _mod.clean_content_text(content_text, title)
    md = _mod.to_markdown(title, cleaned)
    return {
        "uuid": uid,
        "skip_write": uid == ROOT_UUID,
        "markdown": md,
        "title": title,
        "parent_title": _mod.resolve_crm_parent_title(state, uid) if uid != ROOT_UUID else "",
        "url": f"https://collabis.ru{path}",
        "markdown_path": f"storage/collabis-export/pages/{uid}.md",
    }


def manifest(state: dict) -> list[dict]:
    return _mod.build_manifest(state)


def main() -> None:
    cmd = sys.argv[1]
    state = load()
    if cmd == "next":
        path = next_url(state)
        save(state)
        print(json.dumps({"path": path, "url": f"https://collabis.ru{path}" if path else None}))
    elif cmd == "save":
        payload = json.loads(sys.argv[2])
        info = ingest(state, payload["path"], payload["title"], payload.get("links") or [], payload.get("contentText") or "")
        print(json.dumps(info, ensure_ascii=False))
    elif cmd == "finalize":
        m = manifest(state)
        print(json.dumps({"manifest": m, "pages": len(state["pages"]), "errors": state["errors"]}, ensure_ascii=False))
    elif cmd == "reset":
        if STATE_FILE.exists():
            STATE_FILE.unlink()
        print("ok")
    else:
        raise SystemExit(f"unknown: {cmd}")


if __name__ == "__main__":
    main()
