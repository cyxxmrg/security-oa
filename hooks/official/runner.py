#!/usr/bin/env python3
"""official/runner.py — 通用钩子执行器（可被所有官方钩子复用）。"""

from __future__ import annotations

import sys
from collections.abc import Callable, Iterable
from pathlib import Path


def iter_files(paths: Iterable[str]) -> list[Path]:
    out: list[Path] = []
    for raw in paths:
        p = Path(raw)
        if p.exists() and p.is_file():
            out.append(p)
    return out


def patched(paths: Iterable[str], apply_fn: Callable[[list[Path]], int]) -> int:
    """apply_fn(list_of_files) -> int exit code. 对每个文件执行 apply_fn。
    apply_fn 返回非 0 视为失败。
    """
    files = iter_files(paths)
    if not files:
        return 0
    failed = 0
    for p in files:
        try:
            text = p.read_text(encoding="utf-8", errors="replace")
        except OSError as e:
            print(f"  read fail: {p}: {e}", file=sys.stderr)
            failed += 1
            continue
        new = apply_fn(p, text)
        if new is None:
            continue  # 无变更
        if new != text:
            try:
                p.write_text(new, encoding="utf-8")
                print(f"  fix: {p}")
            except OSError as e:
                print(f"  write fail: {p}: {e}", file=sys.stderr)
                failed += 1
    return 0 if failed == 0 else 1


def check(paths: Iterable[str], predicate: Callable[[Path, str], str | None]) -> int:
    """predicate(file, text) -> str if violated else None"""
    failed = 0
    for p in iter_files(paths):
        try:
            text = p.read_text(encoding="utf-8", errors="replace")
        except OSError:
            continue
        verdict = predicate(p, text)
        if verdict:
            print(f"  {p}: {verdict}")
            failed += 1
    return 0 if failed == 0 else 1
