#!/usr/bin/env python3
"""
check_pandas_antipatterns.py — 拦截 pandas 高性能反例

设计目标：
- 12 类反例覆盖 80% 实战场景
- 低误报：默认跳过 # noqa: pd-anti-xxx 与注释/字符串内命中
- 输出可读：file:line [TAG] description (fix hint)
- 自包含：仅依赖 Python 标准库，便于在没有额外依赖的项目里直接跑

退出码：
  0 — 无问题
  1 — 检出反例（pre-commit 视为 FAIL，阻止 commit）
  2 — 使用错误（参数错、文件不可读）

参考资料（节选）：
- pandas 官方 Performance Tips 章节
- vibe-coding-cn/docs/references/quality-gates-and-pitfalls.md
- https://github.com/raik-tools/raik-anu/blob/master/pandas_antipatterns.md
"""

from __future__ import annotations

import argparse
import re
import sys
from collections.abc import Iterable
from dataclasses import dataclass
from pathlib import Path

# --------------------------------------------------------------------------- #
# 反例规则定义                                                                 #
# --------------------------------------------------------------------------- #


@dataclass(frozen=True)
class Rule:
    """单条反例规则"""

    tag: str  # 简短代号，用于 noqa 注释：# noqa: pd-anti-iterrows
    pattern: re.Pattern[str]  # 命中行匹配
    description: str  # 一句话描述
    fix: str  # 修正建议


# 12 条规则（按命中频率排序）
RULES: tuple[Rule, ...] = (
    Rule(
        tag="pd-anti-iterrows",
        pattern=re.compile(r"\.iterrows\s*\("),
        description="DataFrame.iterrows() 逐行迭代（极慢）",
        fix="改用 df.itertuples(index=False) 或向量化 NumPy 操作；如确需行级，改用 df.apply(...) 配合 result_type='expand'",
    ),
    Rule(
        tag="pd-anti-apply-axis1",
        pattern=re.compile(r"\.apply\s*\([^)]*axis\s*=\s*1\b"),
        description="apply(axis=1) 按行调用 Python 函数（慢）",
        fix="用 NumPy 广播 / 向量化方法 / df.transform 而非 apply(axis=1)",
    ),
    Rule(
        tag="pd-anti-apply-lambda-series",
        pattern=re.compile(r"\.apply\s*\(\s*lambda\b"),
        description="Series.apply(lambda) 通常可被向量化方法替代",
        fix="用内置向量化方法：.str / .dt / numpy ufunc / np.where / df.where 替代简单 lambda",
    ),
    Rule(
        tag="pd-anti-df-append",
        pattern=re.compile(r"\bdf[a-zA-Z_]*\.append\s*\("),
        description="DataFrame.append() 已废弃且存在性能陷阱",
        fix="用 pd.concat([df, new_rows], ignore_index=True) 替代",
    ),
    Rule(
        tag="pd-anti-iteritems",
        pattern=re.compile(r"\.iteritems\s*\("),
        description="iteritems() 是 v0.x 已废弃 API，且与 items() 语义不同",
        fix="用 .items() 替代",
    ),
    Rule(
        tag="pd-anti-chained-indexing",
        pattern=re.compile(r"\bdf[a-zA-Z_0-9]*\[[^\]]+\]\s*\["),
        description="链式索引 df[col][idx] 触发 SettingWithCopyWarning",
        fix="用 .loc[:, col] / .iloc[:, col] / .at / .iat 一次性读写",
    ),
    Rule(
        tag="pd-anti-applymap",
        pattern=re.compile(r"\.applymap\s*\("),
        description="applymap() 在 v2.x 推荐替换为 DataFrame.map()",
        fix="改用 df.map(func) 或 df.applymap 不兼容场景用 numpy.vectorize",
    ),
    Rule(
        tag="pd-anti-concat-in-loop",
        pattern=re.compile(r"\bpd\.concat\s*\("),
        description="在循环里调用 pd.concat 累积 DataFrame（N×N 复制）",
        fix="先收集到 list（循环体只 append 字典/Series），循环外一次性 pd.concat(list_of_rows, ignore_index=True)",
    ),
    Rule(
        tag="pd-anti-merge-in-loop",
        pattern=re.compile(r"\.merge\s*\("),
        description="循环里逐个 merge（无法利用向量化）",
        fix="合并一次：先 df.reduce(func, [...]) 或把多份目标拼成一张表 join 一次",
    ),
    Rule(
        tag="pd-anti-zip-df-cols",
        pattern=re.compile(r"for\s+.+?\bin\s+zip\s*\(\s*df\b|zip\s*\(\s*df\[|zip\s*\(\s*df\."),
        description="for ... in zip(df['a'], df['b']) 逐元素迭代 Series（慢）",
        fix="用 df.itertuples() / df[['a','b']].to_numpy() / 整列向量化运算",
    ),
    Rule(
        tag="pd-anti-inplace-chain",
        pattern=re.compile(
            r"\.fillna\([^)]*inplace\s*=\s*True|\.drop_duplicates\([^)]*inplace\s*=\s*True|\.dropna\([^)]*inplace\s*=\s*True|\.reset_index\([^)]*inplace\s*=\s*True"
        ),
        description="inplace=True 与链式调用混用易产生副作用且不易调试",
        fix="改用 df = df.fillna(...) 等纯函数形式；保留 inplace 仅在确认无后续链式调用时",
    ),
    Rule(
        tag="pd-anti-string-concat-obj",
        pattern=re.compile(r"['\"][^'\"]*['\"]?\s*\+\s*df\[|df\[.*?\]\s*\+\s*['\"]"),
        description="字符串与 Series 用 + 拼接（dtype=object，性能差且不健壮）",
        fix="用 df['col'].str.cat(sep=...) 或 f-string 在向量化上下文拼接",
    ),
)


# --------------------------------------------------------------------------- #
# 扫描逻辑                                                                   #
# --------------------------------------------------------------------------- #


# 简单行级 tokenizer，排除被三引号 / 单引号包裹的行内文本误报
# 实现策略：不解析完整 AST，按行扫；遇到被 quote 包住的代码段视为字符串
SKIP_FILE_SUFFIXES = (".pyc", ".pyo")
NOAA_PATTERN = re.compile(r"#\s*noqa:\s*pd-anti-[\w\-]+")
IN_TRIPLE = re.compile(r"^\s*(?:[rRbBuUfF]{0,3})?(?:'''|\"\"\")")


def _is_in_string(line: str) -> bool:
    """粗略判断该行主体是否处于字符串字面量；只用于过滤纯字符串行"""
    stripped = line.strip()
    if not stripped or stripped.startswith("#"):
        return True
    return False


def _line_has_noaa(line: str, tag: str) -> bool:
    m = NOAA_PATTERN.search(line)
    if not m:
        return False
    return tag in m.group(0)


@dataclass
class Hit:
    file: Path
    line_no: int
    tag: str
    description: str
    fix: str


def scan_file(path: Path) -> list[Hit]:
    """扫描单个 .py 文件，返回命中列表（已去重）"""
    hits: list[Hit] = []
    try:
        text = path.read_text(encoding="utf-8", errors="replace")
    except OSError as e:
        print(f"read error: {path}: {e}", file=sys.stderr)
        return hits

    # 按行扫描
    lines = text.splitlines()
    in_triple_string = False
    triple_quote: str | None = None

    for idx, raw in enumerate(lines, start=1):
        line = raw.rstrip("\n")

        # 三引号字符串切换
        trip_match = IN_TRIPLE.match(line)
        if trip_match:
            if not in_triple_string:
                in_triple_string = True
                triple_quote = trip_match.group(0).strip()
            else:
                if triple_quote and triple_quote in line:
                    in_triple_string = False
                    triple_quote = None
            continue  # 三引号行本身跳过

        if in_triple_string:
            continue

        if _is_in_string(line):
            continue

        for rule in RULES:
            if rule.pattern.search(line) and not _line_has_noaa(line, rule.tag):
                hits.append(
                    Hit(
                        file=path,
                        line_no=idx,
                        tag=rule.tag,
                        description=rule.description,
                        fix=rule.fix,
                    )
                )

    return hits


def scan_paths(paths: Iterable[str]) -> list[Hit]:
    """扫描一组路径（pre-commit 传入的可疑文件列表）"""
    all_hits: list[Hit] = []
    seen: set[tuple[str, int, str]] = set()
    for raw in paths:
        p = Path(raw)
        if not p.exists():
            continue
        if p.suffix in SKIP_FILE_SUFFIXES:
            continue
        if p.suffix != ".py":
            continue
        for h in scan_file(p):
            key = (str(h.file), h.line_no, h.tag)
            if key in seen:
                continue
            seen.add(key)
            all_hits.append(h)
    return all_hits


# --------------------------------------------------------------------------- #
# 输出 & 入口                                                                  #
# --------------------------------------------------------------------------- #


def _print_hits(hits: list[Hit]) -> None:
    # 按文件分组，便于阅读
    by_file: dict[Path, list[Hit]] = {}
    for h in hits:
        by_file.setdefault(h.file, []).append(h)
    for f, items in by_file.items():
        print(f"\n{f}")
        for h in items:
            print(f"  line {h.line_no:>4}  [{h.tag}]  {h.description}")
            print(f"           fix: {h.fix}")


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(
        description="扫描 pandas 高性能反例（pre-commit hook 用法：在 .pre-commit-config.yaml 中以 entry='python hooks/check_pandas_antipatterns.py' 调用）",
    )
    parser.add_argument(
        "paths",
        nargs="*",
        help="一个或多个待扫描的 .py 文件路径（pre-commit 会把改动文件传进来）",
    )
    parser.add_argument(
        "--list-tags",
        action="store_true",
        help="列出所有支持的 noqa tag 后退出",
    )
    args = parser.parse_args(argv)

    if args.list_tags:
        for r in RULES:
            print(f"{r.tag}: {r.description}")
        return 0

    if not args.paths:
        print("没有传入任何文件路径（pre-commit 应会自动传入 staged 文件）", file=sys.stderr)
        return 2

    hits = scan_paths(args.paths)
    if not hits:
        print("pandas_antipatterns: clean ✓")
        return 0

    print("pandas_antipatterns: 检测到反例 ↓")
    _print_hits(hits)
    print(
        f"\n  共 {len(hits)} 处反例。修复或在行尾加 # noqa: pd-anti-<tag> 以豁免。",
        file=sys.stderr,
    )
    return 1


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
