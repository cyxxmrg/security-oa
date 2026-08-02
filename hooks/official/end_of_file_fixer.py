#!/usr/bin/env python3
"""end-of-file-fixer：确保文件以单个换行符结尾。"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path


def apply(p: Path, text: str) -> str:
    # 把多个结尾换行收成一个
    return re.sub(r"(?:\r?\n)+$", "\n", text)


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("paths", nargs="*")
    args = ap.parse_args(argv)
    from runner import patched

    return patched(args.paths, apply)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
