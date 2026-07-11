#!/usr/bin/env python3
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
EXPORT = ROOT / "storage" / "collabis-export"
data = json.loads((EXPORT / "process-output.json").read_text(encoding="utf-8"))

for f in data["files"]:
    p = ROOT / f["path"]
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(f["content"], encoding="utf-8")

manifest = data["manifest"]
(EXPORT / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")

stats = data["stats"]
log_lines = [
    f"Total pages crawled: {stats['total_crawled']}",
    f"Manifest entries excluding root: {stats['manifest_entries']}",
    f"Errors: {len(stats['errors'])}",
]
if stats["errors"]:
    log_lines.extend(["", "Errors:"] + [f"  - {e}" for e in stats["errors"]])
(EXPORT / "crawl-log.txt").write_text("\n".join(log_lines) + "\n", encoding="utf-8")

print(len(data["files"]), len(manifest))
