#!/usr/bin/env python3
"""check-case-conflict：检测路径在不同大小写平台下的冲突。简化版：扫描目录里"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("paths", nargs="*")
    args = ap.parse_args(argv)
    bad = 0
    # 按目录分组检测：同一个目录下不允许同名不同大小写的文件
    by_dir: dict[str, dict[str, str]] = {}
    for raw in args.paths:
        p = Path(raw)
        if not p.exists() or p.name.startswith("."):
            continue
        parent = str(p.parent)
        if parent not in by_dir:
            by_dir[parent] = {}
        low = p.name.lower()
        seen = by_dir[parent]
        if low in seen and seen[low] != p.name:
            print(f"    {parent}/  case conflict: {seen[low]} vs {p.name}")
            bad += 1
        else:
            seen[low] = p.name
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
