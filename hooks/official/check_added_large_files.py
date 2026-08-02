#!/usr/bin/env python3
"""check-added-large-files：拦截超过阈值的入库文件。"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("paths", nargs="*")
    ap.add_argument("--maxkb", type=int, default=500)
    args = ap.parse_args(argv)
    bad = 0
    for raw in args.paths:
        p = Path(raw)
        if not p.exists() or not p.is_file():
            continue
        size_kb = p.stat().st_size / 1024
        if size_kb > args.maxkb:
            print(f"  {p}: {size_kb:.1f} KB > {args.maxkb} KB")
            bad += 1
    if bad:
        print(f"check-added-large-files: {bad} files exceed {args.maxkb} KB", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
