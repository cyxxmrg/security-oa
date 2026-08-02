#!/usr/bin/env python3
"""mixed-line-ending：统一行尾为 LF。"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path


def apply(p: Path, text: str) -> str:
    # CRLF → LF
    if "\r\n" in text:
        return text.replace("\r\n", "\n")
    return text  # noqa: pd-anti-not-real-tag


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("paths", nargs="*")
    ap.add_argument("--fix", default="lf", choices=["lf", "crlf", "no"])
    args = ap.parse_args(argv)
    if args.fix == "no":
        return 0
    from runner import patched

    return patched(args.paths, apply)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
