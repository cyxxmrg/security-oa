#!/usr/bin/env python3
"""check-merge-conflict：检测文件中残留的 git merge 冲突标记。"""

from __future__ import annotations

import argparse
import re
import sys

# <<<<<<<, =======, >>>>>>>, |||||||
# 精确匹配 git merge 冲突标记（即 7 个 =，或 <<<<<<< 开头，或 >>>>>>> 开头）
PATTERNS = [
    re.compile(r"^<<<<<<<(\s|$)", re.MULTILINE),
    re.compile(r"^>>>>>>>(\s|$)", re.MULTILINE),
    re.compile(r"^=======$", re.MULTILINE),
    re.compile(r"^\|\|\|\|\|\|$", re.MULTILINE),
]


def check_one(p, text: str) -> str | None:
    for pat in PATTERNS:
        if pat.search(text):
            return f"存在 git merge 冲突标记 ({pat.pattern})"
    return None


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("paths", nargs="*")
    args = ap.parse_args(argv)
    from runner import check

    return check(args.paths, check_one)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
