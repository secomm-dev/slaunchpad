#!/usr/bin/env python3
"""TASK-8V7ANH — live HTML checks with entity decoding (LL-0005).
Usage: check-live.py <file> <expected_vi_string> ; exits non-zero on failure."""
import html
import re
import sys

path, expected = sys.argv[1], sys.argv[2]
raw = open(path, encoding="utf-8", errors="replace").read()
text = html.unescape(raw)

fail = 0

# 1) expected vi string present
n = text.count(expected)
print(f"[{'PASS' if n else 'FAIL'}] expected '{expected}' occurrences={n}")
fail += 0 if n else 1

# 2) English source string absent
n_en = len(re.findall(r"Track your order", text))
print(f"[{'PASS' if not n_en else 'FAIL'}] English 'Track your order' occurrences={n_en} (expect 0)")
fail += 1 if n_en else 0

# 3) context around the tracking anchor (Hyvä link.phtml: class="underline" + title=)
for m in re.finditer(re.escape(expected), text):
    s, e = max(0, m.start() - 160), min(len(text), m.end() + 60)
    ctx = re.sub(r"\s+", " ", text[s:e])
    print("CTX:", ctx)
    break
else:
    print("CTX: expected string not found")

# 4) store locale marker
lang = re.search(r'<html lang="([^"]*)"', text)
print(f"html lang = {lang.group(1) if lang else 'n/a'}")

sys.exit(1 if fail else 0)
