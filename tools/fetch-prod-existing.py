#!/usr/bin/env python3
"""Fetch prod sales book parent/title pairs via prod-plink."""
from __future__ import annotations

import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PLINK = ROOT / "scripts" / "prod-plink.ps1"
OUT = ROOT / "tools" / "prod-existing-utf8.tsv"

SQL = (
    "mysql -u logodmin -pvP1xU4qV0s clear_base --default-character-set=utf8mb4 -N "
    "-e 'SELECT p.title, a.title FROM sales_book_articles a "
    "LEFT JOIN sales_book_articles p ON p.id=a.parent_id ORDER BY a.id'"
)

result = subprocess.run(
    ["powershell", "-NoProfile", "-Command", f"& '{PLINK}' '{SQL}'"],
    capture_output=True,
    cwd=ROOT,
)

text = (result.stdout or b"").decode("utf-8", errors="replace")
lines = []
for line in text.splitlines():
    line = line.strip()
    if not line or line.startswith("mysql:"):
        continue
    if "\t" in line:
        parent, title = line.split("\t", 1)
    else:
        parts = line.split(None, 1)
        if len(parts) != 2:
            continue
        parent, title = parts
    if parent == "NULL":
        parent = ""
    lines.append(f"{parent}\t{title}")

OUT.write_text("\n".join(lines) + ("\n" if lines else ""), encoding="utf-8")
print(f"written {len(lines)} rows to {OUT}")
