#!/usr/bin/env python3
"""
check_secrets.py — 拦截入库的明文密钥 / 凭证 / 高熵随机串

策略：行级正则 + 熵阈值双轨扫描，避免高误报又保留高召回
- 高确定性匹配：常见云厂商 / SDK key 前缀
- 弱确定性 + Shannon 熵：随机串（≥32 base64 字符 / ≥40 hex 字符）

支持行级豁免：行尾加 `# noqa: secret`
"""

from __future__ import annotations

import argparse
import math
import re
import sys
from collections import Counter
from collections.abc import Iterable
from dataclasses import dataclass
from pathlib import Path

SECRET_NOAA = re.compile(r"#\s*noqa:\s*secret")
SKIP_FILE_SUFFIXES = (".pyc", ".pyo", ".lock", ".svg")
SKIP_DIR_PARTS = (".git", "__pycache__", "node_modules", ".venv", "venv")


@dataclass(frozen=True)
class Pattern:
    name: str
    regex: re.Pattern[str]


PATTERNS: tuple[Pattern, ...] = (
    Pattern("aws-access-key", re.compile(r"\bAKIA[0-9A-Z]{16}\b")),
    Pattern("aws-secret-key", re.compile(r"(?i)aws[_\-]?secret[_:=\"'\s]+([A-Za-z0-9/+=]{40})")),
    Pattern("github-token", re.compile(r"\bghp_[A-Za-z0-9]{36}\b")),
    Pattern("github-fine-grained-pat", re.compile(r"\bgithub_pat_[A-Za-z0-9_]{82}\b")),
    Pattern("openai-key", re.compile(r"\bsk-[A-Za-z0-9]{20,}\b")),
    Pattern("anthropic-key", re.compile(r"\bsk-ant-[A-Za-z0-9-]{20,}\b")),
    Pattern("google-api-key", re.compile(r"\bAIza[0-9A-Za-z\-_]{35}\b")),
    Pattern("wechat-appsecret", re.compile(r"(?i)app[_\-]?secret['\"\s:=]+[A-Za-z0-9]{32}")),
    Pattern(
        "private-key-pem",
        re.compile(r"-----BEGIN (?:RSA |EC |DSA |OPENSSH |PGP )?PRIVATE KEY-----"),
    ),
    Pattern(
        "password-assignment",
        re.compile(
            r"(?i)\b(password|passwd|pwd|token|secret|api[_\-]?key)\s*[:=]\s*['\"][^'\"\s]{6,}['\"]"
        ),
    ),
    Pattern(
        "bearer-header",
        re.compile(r"(?i)authorization\s*[:=]\s*['\"]?Bearer\s+[A-Za-z0-9._\-]{20,}"),
    ),
)


# 高熵 token 检测
BASE64_LIKE = re.compile(r"\b[A-Za-z0-9+/=_\-]{32,}\b")
HEX_LIKE = re.compile(r"\b[A-Fa-f0-9]{40,}\b")


def _is_comment_or_string_only(line: str) -> bool:
    s = line.strip()
    if not s:
        return True
    if s.startswith("#"):
        return True
    return False


def _shannon_entropy(s: str) -> float:
    if not s:
        return 0.0
    counts = Counter(s)
    total = len(s)
    return -sum((c / total) * math.log2(c / total) for c in counts.values())


def _scan_entropy(line: str) -> list[str]:
    """检测高熵字符串。仅对 base64_like / hex_like 字符串做熵值校准。"""
    findings: list[str] = []
    for m in BASE64_LIKE.finditer(line):
        tok = m.group(0)
        if len(tok) >= 32 and _shannon_entropy(tok) >= 4.5:
            findings.append("high-entropy-token")
            return findings  # 单行只报告一次
    for m in HEX_LIKE.finditer(line):
        tok = m.group(0)
        if len(tok) >= 40 and _shannon_entropy(tok) >= 3.0:
            findings.append("high-entropy-hex")
            return findings
    return findings


def scan_file(path: Path) -> list[tuple[int, str, str]]:
    """返回 [(line_no, kind, snippet)]"""
    out: list[tuple[int, str, str]] = []
    try:
        text = path.read_text(encoding="utf-8", errors="replace")
    except OSError:
        return out
    for idx, raw in enumerate(text.splitlines(), start=1):
        line = raw.rstrip()
        if SECRET_NOAA.search(line):
            continue
        if _is_comment_or_string_only(line):
            continue
        for p in PATTERNS:
            if p.regex.search(line):
                out.append((idx, p.name, line[:160]))
                break
        else:
            # 退而求其次：高熵检测
            for tag in _scan_entropy(line):
                # 过滤合法场景：哈希（短）、UUID、版本号通常熵不够高
                out.append((idx, tag, line[:160]))
                break
    return out


def scan(paths: Iterable[str]) -> int:
    bad = 0
    seen_keys: set[tuple[str, int, str]] = set()
    for raw in paths:
        p = Path(raw)
        if not p.exists() or p.is_dir():
            continue
        if p.suffix in SKIP_FILE_SUFFIXES:
            continue
        if any(part in SKIP_DIR_PARTS for part in p.parts):
            continue
        for line_no, kind, snippet in scan_file(p):
            key = (str(p), line_no, kind)
            if key in seen_keys:
                continue
            seen_keys.add(key)
            print(f"\n{p}:{line_no}  [{kind}]")
            print(f"   {snippet}")
            print(
                "   fix: 移到 .env / .gitignore 锁定；或用 secret manager；行尾加 # noqa: secret 临时豁免"
            )
            bad += 1
    if bad:
        print(f"\n  共 {bad} 处疑似密钥。", file=sys.stderr)
        return 1
    print("secrets: clean ✓")
    return 0


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(
        description="扫描入库凭证（pre-commit hook：entry='python hooks/check_secrets.py'）",
    )
    parser.add_argument("paths", nargs="*")
    args = parser.parse_args(argv)
    return scan(args.paths)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
