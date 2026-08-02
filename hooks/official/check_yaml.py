#!/usr/bin/env python3
"""check-yaml：用 PyYAML 解析传入的 YAML 文件以确保有效。"""

from __future__ import annotations

import argparse
import sys

import yaml


def check_one(p, text: str) -> str | None:
    try:
        yaml.safe_load_all(text)
    except yaml.YAMLError as e:
        return f"YAML 解析失败: {e}"
    return None


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("paths", nargs="*")
    ap.add_argument("--allow-multiple-documents", action="store_true")
    args = ap.parse_args(argv)
    from runner import check

    return check(args.paths, check_one)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
