#!/usr/bin/env python3
"""check-toml：解析 TOML 文件以确保有效（Python 3.11+ tomllib）。"""

from __future__ import annotations

import argparse
import sys


def check_one(p, text: str) -> str | None:
    try:
        import tomllib

        tomllib.loads(text)
    except ImportError:
        try:
            import tomli

            tomli.loads(text)
        except ImportError:
            return "TOML 校验需要 tomllib (3.11+) 或 tomli 包；当前 Python 不支持"
        except Exception as e:
            return f"TOML 解析失败: {e}"
    except Exception as e:
        return f"TOML 解析失败: {e}"
    return None


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("paths", nargs="*")
    args = ap.parse_args(argv)
    from runner import check

    return check(args.paths, check_one)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
