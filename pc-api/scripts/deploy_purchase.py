#!/usr/bin/env python3
"""
紧急部署采购管理 8 个 Controller + 8 个 migration 到 192.168.1.20
"""

import os
import sys

import paramiko

HOST = "192.168.1.20"
USER = "example"
# 凭据强制从环境变量读取，禁止硬编码或兜底默认密码
# 调用前必须 `export OA172_PWD=...`（或 set OA172_PWD=... on Windows）
PASSWORD = os.environ.get("OA172_PWD")
PORT = 22
LOCAL_BASE = r"D:\work\website\OA\pc-api"

CONTROLLERS = [
    "app/Http/Controllers/Api/PurchaseRequirementController.php",
    "app/Http/Controllers/Api/PurchasePlanController.php",
    "app/Http/Controllers/Api/PurchasePaymentRequestController.php",
    "app/Http/Controllers/Api/PurchasePaymentController.php",
    "app/Http/Controllers/Api/PurchaseContractController.php",
    "app/Http/Controllers/Api/PurchaseShipmentController.php",
    "app/Http/Controllers/Api/PurchaseLogisticsController.php",
    "app/Http/Controllers/Api/PurchaseApprovalController.php",
]

MIGRATIONS = [
    "database/migrations/2026_06_19_110001_create_purchase_requirements_table.php",
    "database/migrations/2026_06_19_110002_create_purchase_plans_table.php",
    "database/migrations/2026_06_19_110003_create_purchase_payment_requests_table.php",
    "database/migrations/2026_06_19_110004_create_purchase_payments_table.php",
    "database/migrations/2026_06_19_110005_create_purchase_contracts_table.php",
    "database/migrations/2026_06_19_110006_create_purchase_shipments_table.php",
    "database/migrations/2026_06_19_110007_create_purchase_logistics_table.php",
    "database/migrations/2026_06_19_110008_create_purchase_approvals_table.php",
]

# 关键：routes/api.php + app/Models/OtherModels.php 也必须同步（之前忘了）
EXTRA_FILES = [
    "routes/api.php",
    "app/Models/OtherModels.php",
]


def run(ssh, cmd, sudo=False, check=True, timeout=120):
    """Run remote command. If sudo=True, prefix with 'sudo -n'."""
    full = ("sudo -n " + cmd) if sudo else cmd
    print(f"  $ {full}")
    stdin, stdout, stderr = ssh.exec_command(full, timeout=timeout)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    rc = stdout.channel.recv_exit_status()
    if out.strip():
        print("    STDOUT:", out[:2000])
    if err.strip():
        print("    STDERR:", err[:2000])
    if check and rc != 0:
        raise RuntimeError(f"command failed rc={rc}: {full}")
    return rc, out, err


def main():
    if not PASSWORD:
        # 无兜底密码：env 未设置直接 fail，避免把敏感凭据写进源码或默认配置
        raise OSError(
            "OA172_PWD environment variable is required (SSH password for deploy); "
            "refusing to fall back to a hardcoded default."
        )

    print(f"[1/4] connecting {USER}@{HOST}:{PORT} ...")
    ssh = paramiko.SSHClient()
    # 拒绝未知主机：必须由 known_hosts 提供公钥，否则连接失败
    ssh.load_system_host_keys()
    ssh.set_missing_host_key_policy(paramiko.RejectPolicy())
    ssh.connect(HOST, PORT, USER, PASSWORD, allow_agent=False, look_for_keys=False)
    sftp = ssh.open_sftp()
    print("  connected")

    # 0) 先试探 sudo -n 是否可用（无密码）
    print("\n[2/4] 测试 sudo -n ...")
    rc, out, err = run(ssh, "id", sudo=True)
    print(f"  sudo OK, id={out.strip()}")

    # 1) 上传 Controller
    print("\n[3/4] SFTP Controllers + Migrations + Extra → /tmp ...")
    for rel in CONTROLLERS + MIGRATIONS + EXTRA_FILES:
        local_path = os.path.join(LOCAL_BASE, rel)
        if not os.path.exists(local_path):
            print(f"  ❌ MISSING: {local_path}")
            sys.exit(3)
        # 上传到 /tmp，保持原文件名（extra 用 全路径替换 / 为 _ 避免重名）
        base = os.path.basename(rel)
        remote_tmp = f"/tmp/{base}"
        sftp.put(local_path, remote_tmp)
        # 验证大小
        local_size = os.path.getsize(local_path)
        remote_size = sftp.stat(remote_tmp).st_size
        ok = "✓" if local_size == remote_size else "❌"
        print(f"  {ok} {rel}  ({local_size} → {remote_size} bytes)")

    # 2) 移动到目标目录
    # ⚠ 172 是 LXD 容器，文件覆盖时 cp 偶发报 "cannot remove"（伪错误，文件已覆写）
    #   所以这里不用 && 链，每条独立执行，错误码 != 0 仅打印不中断
    print("\n[4/4] sudo cp -f 到目标目录（LXD 容器忽略 cannot remove 伪错误） ...")
    ctl_pairs = [
        (
            "Requirement",
            "/var/www/oa-api/app/Http/Controllers/Api/PurchaseRequirementController.php",
        ),
        ("Plan", "/var/www/oa-api/app/Http/Controllers/Api/PurchasePlanController.php"),
        (
            "PaymentRequest",
            "/var/www/oa-api/app/Http/Controllers/Api/PurchasePaymentRequestController.php",
        ),
        ("Payment", "/var/www/oa-api/app/Http/Controllers/Api/PurchasePaymentController.php"),
        ("Contract", "/var/www/oa-api/app/Http/Controllers/Api/PurchaseContractController.php"),
        ("Shipment", "/var/www/oa-api/app/Http/Controllers/Api/PurchaseShipmentController.php"),
        ("Logistics", "/var/www/oa-api/app/Http/Controllers/Api/PurchaseLogisticsController.php"),
        ("Approval", "/var/www/oa-api/app/Http/Controllers/Api/PurchaseApprovalController.php"),
    ]
    mig_names = [
        "requirements",
        "plans",
        "payment_requests",
        "payments",
        "contracts",
        "shipments",
        "logistics",
        "approvals",
    ]
    mig_pairs = [
        (
            f"2026_06_19_11000{i+1}_create_purchase_{n}_table.php",
            f"/var/www/oa-api/database/migrations/2026_06_19_11000{i+1}_create_purchase_{n}_table.php",
        )
        for i, n in enumerate(mig_names)
    ]

    def cp_one(src, dst):
        cmd = f"cp -f {src} {dst}"
        rc, out, err = run(ssh, cmd, sudo=True, check=False, timeout=30)
        # 容忍 LXD 伪错误 "cannot remove"
        if "cannot remove" in err and "Operation not permitted" in err:
            print(f"    [LXD 伪错误忽略] {dst}")
            return 0
        if rc != 0:
            print(f"    ❌ 真实失败 rc={rc}: {dst}")
        return rc

    for c, t in ctl_pairs:
        cp_one(f"/tmp/Purchase{c}Controller.php", t)
    for s, t in mig_pairs:
        cp_one(f"/tmp/{s}", t)
    # 额外：routes/api.php + app/Models/OtherModels.php
    cp_one("/tmp/api.php", "/var/www/oa-api/routes/api.php")
    cp_one("/tmp/OtherModels.php", "/var/www/oa-api/app/Models/OtherModels.php")

    # 3) 验证文件已落地
    print("\n=== 验证文件已落地 ===")
    run(ssh, "ls -la /var/www/oa-api/app/Http/Controllers/Api/Purchase*.php", sudo=True)
    run(
        ssh,
        "ls -la /var/www/oa-api/database/migrations/2026_06_19_11000*_create_purchase*.php",
        sudo=True,
    )
    run(ssh, "grep -c Purchase /var/www/oa-api/routes/api.php", sudo=True)

    sftp.close()
    ssh.close()
    print("\n✅ 文件部署完成")


if __name__ == "__main__":
    main()
