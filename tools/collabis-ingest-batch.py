#!/usr/bin/env python3
"""Process batched Collabis crawl results from browser CDP."""

from __future__ import annotations

import json
import sys
from pathlib import Path

import importlib.util

_spec = importlib.util.spec_from_file_location(
    "collabis_bfs_crawl",
    Path(__file__).resolve().parent / "collabis-bfs-crawl.py",
)
_mod = importlib.util.module_from_spec(_spec)
assert _spec and _spec.loader
_spec.loader.exec_module(_mod)
add_children_to_queue = _mod.add_children_to_queue
load_state = _mod.load_state
process_page = _mod.process_page
save_state = _mod.save_state


def ingest_batch(pages: list[dict]) -> None:
    state = load_state()
    for item in pages:
        path = item.get("path", "")
        if not path:
            continue
        if item.get("error"):
            state["errors"].append(f"{path}: {item['error']}")
            if path not in state["visited"]:
                state["visited"].append(path)
            continue
        process_page(
            state,
            path,
            item.get("title") or "Untitled",
            item.get("links") or [],
            item.get("contentText") or "",
        )
        if path not in state["visited"]:
            state["visited"].append(path)
        add_children_to_queue(state, path, item.get("links") or [])
    save_state(state)


def main() -> None:
    data = json.loads(sys.stdin.read())
    pages = data if isinstance(data, list) else data.get("pages", [])
    ingest_batch(pages)
    state = load_state()
    print(json.dumps({"saved": len(pages), "total_pages": len(state["pages"]), "errors": len(state["errors"])}))


if __name__ == "__main__":
    main()
