#!/usr/bin/env python3
"""Seed Hive Mind architecture notes into Obsidian vault (UTF-8 safe on Windows)."""
from __future__ import annotations

import re
from pathlib import Path

VAULT = Path("C:/Users/tr0mb/Yandex.Disk/Exchange")
BASE = VAULT / "CRM" / "v5-local"
PS1 = Path(__file__).with_name("hivemind-seed-v5-docs.ps1")


def write_note(relative: str, body: str) -> None:
    path = BASE / relative.replace("/", "\\")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(body.lstrip("\n"), encoding="utf-8")
    print(f"  + {relative}")


def main() -> None:
    text = PS1.read_text(encoding="utf-8")

    for sub in ("Systems", "Components", "Decisions", "Interfaces", "Constraints"):
        (BASE / sub).mkdir(parents=True, exist_ok=True)

    # Do not overwrite vault config.json вЂ” template is managed via hivemind CLI.

    # Write-Note 'path' @' ... '@
    for match in re.finditer(
        r"Write-Note\s+'([^']+)'\s+@'([\s\S]*?)'@",
        text,
    ):
        write_note(match.group(1), match.group(2))

    # $components hashtable entries: 'File.md' = @' ... '@
    components_block = re.search(r"\$components\s*=\s*@\{([\s\S]*?)\n\}", text)
    if components_block:
        block = components_block.group(1)
        for match in re.finditer(r"'([^']+\.md)'\s*=\s+@'([\s\S]*?)'@", block):
            write_note(f"Components/{match.group(1)}", match.group(2))

    count = len(list(BASE.rglob("*.md")))
    print(f"\nDone: {count} markdown files under {BASE}")


if __name__ == "__main__":
    main()

