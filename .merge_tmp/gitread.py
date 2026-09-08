#!/usr/bin/env python3
# Minimal read-only git object reader: loose + pack(v2 idx), delta resolution.
import zlib, os, struct, sys

GITDIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", ".git")

def read_loose(sha):
    p = os.path.join(GITDIR, "objects", sha[:2], sha[2:])
    if not os.path.exists(p):
        return None
    data = zlib.decompress(open(p, "rb").read())
    nul = data.index(b"\x00")
    hdr = data[:nul].decode()
    kind, size = hdr.split(" ")
    return kind, data[nul+1:]

class Pack:
    def __init__(self, idxpath):
        self.idxpath = idxpath
        self.packpath = idxpath[:-4] + ".pack"
        self.off = {}
        self._load_idx()

    def _load_idx(self):
        d = open(self.idxpath, "rb").read()
        magic = d[:4]
        if magic == b"\xfftOc":
            ver = struct.unpack(">I", d[4:8])[0]
            assert ver == 2, f"idx version {ver}"
            pos = 8
            fanout = struct.unpack(">256I", d[pos:pos+1024]); pos += 1024
            n = fanout[255]
            shas = [d[pos+i*20:pos+(i+1)*20].hex() for i in range(n)]; pos += n*20
            pos += n*4  # crc
            offs = struct.unpack(f">{n}I", d[pos:pos+n*4]); pos += n*4
            large = []
            if pos + 8 <= len(d):
                rem = len(d) - pos - 40  # trailer
                large = struct.unpack(f">{rem//8}Q", d[pos:pos+rem])
            li = 0
            for sha, o in zip(shas, offs):
                if o & 0x80000000:
                    o = large[li]; li += 1
                self.off[sha] = o
        else:
            # v1 idx: fanout then shas+offsets interleaved
            pos = 4*256
            fanout = struct.unpack(">256I", d[:pos])
            n = fanout[255]
            self.off = {}
            for i in range(n):
                sha = d[pos+i*24: pos+i*24+20].hex()
                o = struct.unpack(">I", d[pos+i*24+20:pos+i*24+24])[0]
                self.off[sha] = o

    def raw_at(self, offset):
        """Return (type, extra, payload_after_header_and_prefix).
        type: 1 commit,2 tree,3 blob,6 ofs_delta(extra=back-offset),7 ref_delta(extra=base sha hex)"""
        f = open(self.packpath, "rb")
        f.seek(offset)
        b0 = f.read(1)[0]
        typ = (b0 >> 4) & 7
        size = b0 & 15
        shift = 4
        c = b0
        while c & 0x80:
            c = f.read(1)[0]
            size |= (c & 0x7f) << shift
            shift += 7
        extra = None
        if typ == 6:  # OFS_DELTA: base offset varint
            c = f.read(1)[0]
            ofs = c & 0x7f
            while c & 0x80:
                c = f.read(1)[0]
                ofs = ((ofs + 1) << 7) | (c & 0x7f)
            extra = ofs
        elif typ == 7:  # REF_DELTA: base sha
            extra = f.read(20).hex()
        try:
            data = zlib.decompressobj().decompress(f.read())
        except zlib.error as e:
            raise RuntimeError(
                f"zlib error at pack {self.packpath} offset {offset} type {typ} err={e}"
            )
        return typ, extra, data

    def resolve_at(self, offset, _depth=0):
        typ, extra, data = self.raw_at(offset)
        if typ == 7:
            base_type, base = self.resolve(extra, _depth + 1)
            return base_type, apply_delta(base, data)
        if typ == 6:
            base_type, base = self.resolve_at(offset - extra, _depth + 1)
            return base_type, apply_delta(base, data)
        return typ, data

    def resolve(self, sha, _depth=0):
        if sha in self.off:
            return self.resolve_at(self.off[sha], _depth)
        return None

    def get(self, sha):
        return self.resolve(sha)

def apply_delta(base, delta):
    out = bytearray()
    pos = 0
    # base size varint
    c = delta[pos]; pos += 1
    bsize = c & 0x7f
    while c & 0x80:
        c = delta[pos]; pos += 1
        bsize = ((bsize + 1) << 7) | (c & 0x7f)
    # result size varint
    c = delta[pos]; pos += 1
    rsize = c & 0x7f
    while c & 0x80:
        c = delta[pos]; pos += 1
        rsize = ((rsize + 1) << 7) | (c & 0x7f)
    while pos < len(delta):
        op = delta[pos]; pos += 1
        if op & 0x80:
            cp_off = 0; cp_size = 0
            if op & 0x01: cp_off |= delta[pos]; pos += 1
            if op & 0x02: cp_off |= delta[pos] << 8; pos += 1
            if op & 0x04: cp_off |= delta[pos] << 16; pos += 1
            if op & 0x08: cp_off |= delta[pos] << 24; pos += 1
            if op & 0x10: cp_size |= delta[pos]; pos += 1
            if op & 0x20: cp_size |= delta[pos] << 8; pos += 1
            if op & 0x40: cp_size |= delta[pos] << 16; pos += 1
            if cp_size == 0: cp_size = 0x10000
            out += base[cp_off:cp_off+cp_size]
        elif op:
            out += delta[pos:pos+op]; pos += op
    return bytes(out)

PACKS = []
for f in os.listdir(os.path.join(GITDIR, "objects", "pack")):
    if f.endswith(".idx"):
        try:
            PACKS.append(Pack(os.path.join(GITDIR, "objects", "pack", f)))
        except Exception as e:
            print("skip pack", f, e, file=sys.stderr)

def read_obj(sha):
    r = read_loose(sha)
    if r: return r
    for p in PACKS:
        r = p.get(sha)
        if r:
            # normalize numeric pack type to string kind
            kind = {1: "commit", 2: "tree", 3: "blob", 4: "tag"}.get(r[0], str(r[0]))
            return (kind, r[1])
    return None

def walk_tree(tree_sha, parts):
    cur = tree_sha
    for idx, part in enumerate(parts):
        r = read_obj(cur)
        if not r or r[0] != "tree":
            return None
        body = r[1]
        i = 0; found = None
        while i < len(body):
            sp = body.index(b" ", i)
            ne = body.index(b"\x00", sp)
            mode = body[i:sp].decode()
            name = body[sp+1:ne].decode("utf-8", "replace")
            sha = body[ne+1:ne+21].hex()
            if name == part:
                if idx == len(parts)-1:
                    return mode, sha
                found = sha
                break
            i = ne + 21
        if found is None:
            return None
        cur = found
    return None

def commit_tree(sha):
    r = read_obj(sha)
    assert r and r[0] == "commit", f"not commit {sha}"
    for line in r[1].split(b"\n"):
        if line.startswith(b"tree "):
            return line[5:].decode()

def main():
    theirs_commit = "966daa9785bbec64129c8912fc011f54e39f1326"
    head_commit = "0abfacb9c1ad0c3aad816bb6ee8494ddeac8f2d1"
    path = ["pc-api", "app", "Services", "PurchaseFlowService.php"]
    outdir = os.path.dirname(os.path.abspath(__file__))
    for label, commit in [("HEAD", head_commit), ("ORIGIN", theirs_commit)]:
        tree = commit_tree(commit)
        res = walk_tree(tree, path)
        if not res:
            print(label, "NOT FOUND")
            continue
        mode, sha = res
        r = read_obj(sha)
        print(label, "blob", sha, "type", r[0], "size", len(r[1]))
        open(os.path.join(outdir, f"{label}_PurchaseFlowService.php"), "wb").write(r[1])

if __name__ == "__main__":
    main()
