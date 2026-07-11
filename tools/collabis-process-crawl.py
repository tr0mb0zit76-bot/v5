#!/usr/bin/env python3
"""Process crawl-raw.json into markdown files and manifest (stdout JSON for Write batch)."""

from __future__ import annotations

import importlib.util
import json
import sys
from pathlib import Path

ROOT_UUID = "fa60d7c5-020b-452e-a340-41f053e6eb65"
EXPORT = Path(__file__).resolve().parent.parent / "storage" / "collabis-export"
RAW = EXPORT / "crawl-raw.json"

_spec = importlib.util.spec_from_file_location(
    "collabis_bfs_crawl", Path(__file__).resolve().parent / "collabis-bfs-crawl.py"
)
_mod = importlib.util.module_from_spec(_spec)
assert _spec and _spec.loader
_spec.loader.exec_module(_mod)


def build_state(data: dict) -> dict:
    state = {"queue": [], "visited": [], "pages": {}, "parents": {}, "errors": []}
    parents = data.get("parents") or {}
    for child_id, parent_path in parents.items():
        state["parents"][child_id] = parent_path
    for item in data.get("results") or []:
        path = item.get("path") or ""
        uid = path.strip("/")
        if item.get("error"):
            state["errors"].append(f"{path}: {item['error']}")
        title = item.get("title") or "Untitled"
        links = item.get("links") or []
        content = item.get("contentText") or ""
        _mod.process_page(state, path, title, links, content)
        state["visited"].append(path)
    return state


def main() -> None:
    cdp_path = Path(sys.argv[1]) if len(sys.argv) > 1 else None
    if cdp_path and cdp_path.exists():
        raw = json.loads(cdp_path.read_text(encoding="utf-8"))
        val = raw["result"]["value"] if "result" in raw and isinstance(raw["result"], dict) else raw
        if isinstance(val, dict) and "value" in val:
            val = val["value"]
        data = json.loads(val) if isinstance(val, str) else val
        RAW.write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")
    else:
        data = json.loads(RAW.read_text(encoding="utf-8"))

    state = build_state(data)
    manifest = _mod.build_manifest(state)

    files = []
    for uid, page in state["pages"].items():
        if uid == ROOT_UUID:
            continue
        cleaned = _mod.clean_content_text(
            next((r.get("contentText") or "" for r in data["results"] if r.get("id") == uid), ""),
            page["title"],
        )
        md = _mod.to_markdown(page["title"], cleaned)
        files.append({
            "path": f"storage/collabis-export/pages/{uid}.md",
            "content": md,
        })

    out = {
        "files": files,
        "manifest": manifest,
        "stats": {
            "total_crawled": len(state["pages"]),
            "manifest_entries": len(manifest),
            "errors": state["errors"],
        },
    }
    (EXPORT / "process-output.json").write_text(json.dumps(out, ensure_ascii=False), encoding="utf-8")
    print(json.dumps({"files": len(files), "manifest": len(manifest), "errors": len(state["errors"])}))


if __name__ == "__main__":
    main()
