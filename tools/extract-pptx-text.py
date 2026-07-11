#!/usr/bin/env python3
"""Extract text from a PPTX into a UTF-8 text file."""

from __future__ import annotations

import re
import sys
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

A_NS = "{http://schemas.openxmlformats.org/drawingml/2006/main}"


def extract(pptx_path: Path) -> str:
    with zipfile.ZipFile(pptx_path) as z:
        slides = sorted(
            [n for n in z.namelist() if re.match(r"ppt/slides/slide\d+\.xml$", n)],
            key=lambda x: int(re.search(r"(\d+)", x).group(1)),
        )
        blocks: list[str] = [f"SLIDES={len(slides)}", ""]
        for i, name in enumerate(slides, 1):
            root = ET.fromstring(z.read(name))
            paras: list[str] = []
            for p in root.iter(f"{A_NS}p"):
                parts: list[str] = []
                for t in p.iter(f"{A_NS}t"):
                    if t.text:
                        parts.append(t.text)
                line = "".join(parts).strip()
                if line:
                    paras.append(line)
            blocks.append(f"===== SLIDE {i} =====")
            blocks.extend(paras if paras else ["(empty)"])
            blocks.append("")
        return "\n".join(blocks)


def main() -> int:
    pptx = Path(sys.argv[1])
    out = Path(sys.argv[2]) if len(sys.argv) > 2 else Path("pptx-extract.txt")
    text = extract(pptx)
    out.write_text(text, encoding="utf-8")
    print(f"wrote {out} ({len(text)} chars)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
