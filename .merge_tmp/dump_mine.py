#!/usr/bin/env python3
"""Dump HEAD / ORIGIN versions of the two frontend files I must merge-resolve."""
import os, sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import gitread  # noqa: E402

OUT = os.path.dirname(os.path.abspath(__file__))
HEAD = "0abfacb9c1ad0c3aad816bb6ee8494ddeac8f2d1"
ORIGIN = "966daa9785bbec64129c8912fc011f54e39f1326"

TARGETS = [
    ("pc-web/src/api/portal-tender.ts", "portal-tender"),
    ("pc-web/src/utils/auth.ts", "auth"),
]


def dump(label, commit, relpath, outname):
    parts = relpath.split("/")
    tree = gitread.commit_tree(commit)
    res = gitread.walk_tree(tree, parts)
    if not res:
        print(label, relpath, "NOT FOUND")
        return
    mode, sha = res
    r = gitread.read_obj(sha)
    if not r:
        print(label, relpath, "OBJ MISSING", sha)
        return
    dest = os.path.join(OUT, f"{outname}.{label}.ts")
    with open(dest, "wb") as f:
        f.write(r[1])
    print(label, relpath, "->", dest, "size", len(r[1]), "mode", mode)


if __name__ == "__main__":
    for relpath, outname in TARGETS:
        for label, commit in [("HEAD", HEAD), ("ORIGIN", ORIGIN)]:
            dump(label, commit, relpath, outname)
