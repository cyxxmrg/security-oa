#!/usr/bin/env python3
"""detect-private-key：扫常见私钥 BEGIN 标记。"""

from __future__ import annotations

import argparse
import re
import sys

# 检测如下私钥 BEGIN 标记
PATTERNS = [
    re.compile(r"-----BEGIN (RSA |EC |DSA |OPENSSH |PGP )?PRIVATE KEY-----"),
]


def check_one(p, text: str) -> str | None:
    for pat in PATTERNS:
        m = pat.search(text)
        if m:
            return f"检测到私钥: {m.group(0)!r}"
    return None


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("paths", nargs="*")
    args = ap.parse_args(argv)
    from runner import check

    return check(args.paths, check_one)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
