#!/usr/bin/env python3
"""Generate CDP extract expression for a batch of Collabis paths."""

from __future__ import annotations

import json
import sys
from pathlib import Path

PATHS_FILE = Path(__file__).resolve().parent.parent / "storage" / "collabis-export" / "all-paths.json"

EXTRACT_FN = r"""
async function extractPath(path) {
  return new Promise((resolve) => {
    const iframe = document.createElement('iframe');
    iframe.style.cssText = 'position:fixed;left:-9999px;width:1px;height:1px;opacity:0';
    iframe.src = path;
    const timeout = setTimeout(() => {
      try { iframe.remove(); } catch (_) {}
      resolve({ path, error: 'timeout' });
    }, 15000);
    iframe.onload = async () => {
      await new Promise((r) => setTimeout(r, 2000));
      try {
        const doc = iframe.contentDocument;
        const uuidRe = /^\/[0-9a-f-]{36}$/i;
        const links = [...new Set([...doc.querySelectorAll('a[href^="/"]')]
          .map((a) => a.getAttribute('href'))
          .filter((h) => h && uuidRe.test(h)))];
        const bodyEls = [...doc.querySelectorAll('[class*="is_BodyText"]')];
        let contentText = bodyEls.map((el) => el.innerText.trim()).filter(Boolean).join('\n\n');
        if (!contentText.trim()) {
          const skip = ['Логистические решения', 'Спросите Collabis AI', 'Достигнут лимит'];
          contentText = doc.body.innerText.split('\n')
            .map((l) => l.trim())
            .filter((l) => l && !skip.some((p) => l.includes(p)))
            .join('\n');
        }
        resolve({ path, title: doc.title || 'Untitled', links, contentText });
      } catch (e) {
        resolve({ path, error: String(e) });
      } finally {
        clearTimeout(timeout);
        try { iframe.remove(); } catch (_) {}
      }
    };
    document.body.appendChild(iframe);
  });
}
"""


def main() -> None:
    start = int(sys.argv[1]) if len(sys.argv) > 1 else 0
    count = int(sys.argv[2]) if len(sys.argv) > 2 else 20
    paths = json.loads(PATHS_FILE.read_text(encoding="utf-8-sig"))
    batch = paths[start : start + count]
    expr = (
        EXTRACT_FN
        + "(async () => { const paths = "
        + json.dumps(batch, ensure_ascii=False)
        + "; const results = []; for (const p of paths) { results.push(await extractPath(p)); } return JSON.stringify(results); })()"
    )
    print(expr)


if __name__ == "__main__":
    main()
