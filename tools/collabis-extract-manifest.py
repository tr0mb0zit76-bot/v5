#!/usr/bin/env python3
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
EXPORT = ROOT / "storage" / "collabis-export"
data = json.loads((EXPORT / "process-output.json").read_text(encoding="utf-8"))
manifest = data["manifest"]
stats = data["stats"]
(EXPORT / "manifest-only.json").write_text(
    json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8"
)
log = "\n".join([
    f"Total pages crawled: {stats['total_crawled']}",
    f"Manifest entries excluding root: {stats['manifest_entries']}",
    f"Errors: {len(stats['errors'])}",
    "",
]) + "\n"
(EXPORT / "crawl-log-only.txt").write_text(log, encoding="utf-8")
print(len(manifest))
