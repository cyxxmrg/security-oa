#!/usr/bin/env python3
"""check-json：用 json 解析以确保有效。"""

from __future__ import annotations

import argparse
import json
import sys


def check_one(p, text: str) -> str | None:
    try:
        json.loads(text)
    except json.JSONDecodeError as e:
        return f"JSON 解析失败: {e}"
    return None


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("paths", nargs="*")
    args = ap.parse_args(argv)
    from runner import check

    return check(args.paths, check_one)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
