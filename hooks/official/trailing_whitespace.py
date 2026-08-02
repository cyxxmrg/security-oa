#!/usr/bin/env python3
"""trailing-whitespace：删除每行尾随的空白字符（保留换行）。"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

_TRAILING = re.compile(r"[ \t]+(?=\r?\n|$)")


def apply(p: Path, text: str) -> str:
    return _TRAILING.sub("", text)


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("paths", nargs="*")
    args = ap.parse_args(argv)
    from runner import patched

    code = patched(args.paths, apply)
    if code:
        print(f"trailing-whitespace: {code} files failed", file=sys.stderr)
    return code


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
