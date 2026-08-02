#!/usr/bin/env python3
"""重排 migration + 重新部署 + migrate"""

import os

import paramiko

HOST = "192.168.1.20"
USER = "example"
# 凭据强制从环境变量读取，禁止硬编码或兜底默认密码
PWD = os.environ.get("OA172_PWD")
if not PWD:
    raise OSError(
        "OA172_PWD environment variable is required (SSH password for deploy); "
        "refusing to fall back to a hardcoded default."
    )
LOCAL_BASE = r"D:\work\website\OA\pc-api"

ssh = paramiko.SSHClient()
# 拒绝未知主机：必须由 known_hosts 提供公钥，否则连接失败
ssh.load_system_host_keys()
ssh.set_missing_host_key_policy(paramiko.RejectPolicy())
ssh.connect(HOST, 22, USER, PWD, allow_agent=False, look_for_keys=False)


def run(c, check=False):
    s = ssh.exec_command(c)
    o = s[1].read().decode()
    e = s[2].read().decode()
    rc = s[1].channel.recv_exit_status()
    return rc, o, e


# 1) 删 172 旧 purchase migration（requirements/plans 留下，新顺序补上）
rc, o, e = run(
    "sudo -n bash -c '"
    "rm -f /var/www/oa-api/database/migrations/2026_06_19_110003_create_purchase_payment_requests_table.php "
    "/var/www/oa-api/database/migrations/2026_06_19_110004_create_purchase_payments_table.php "
    "/var/www/oa-api/database/migrations/2026_06_19_110005_create_purchase_contracts_table.php "
    "/var/www/oa-api/database/migrations/2026_06_19_110006_create_purchase_shipments_table.php "
    "/var/www/oa-api/database/migrations/2026_06_19_110007_create_purchase_logistics_table.php "
    "/var/www/oa-api/database/migrations/2026_06_19_110008_create_purchase_approvals_table.php "
    "&& echo cleared'"
)
print(f"  rm 旧文件 rc={rc} {o.strip()}")

# 2) 删 失败记录的 migration 状态
rc, o, e = run(
    "cd /var/www/oa-api && sudo -n -u www-data psql -h 127.0.0.1 -U oa_user -d security_oa "
    "-c \"DELETE FROM migrations WHERE migration LIKE '2026_06_19_11000%purchase%';\" 2>&1 | head -5"
)
print(f"  DELETE FROM migrations rc={rc} {o.strip()} {e.strip()}")

# 3) SFTP 上传 6 个重命名后的 migration
sftp = ssh.open_sftp()
files = [
    "2026_06_19_110003_create_purchase_contracts_table.php",
    "2026_06_19_110005_create_purchase_payment_requests_table.php",
    "2026_06_19_110006_create_purchase_payments_table.php",
    "2026_06_19_110007_create_purchase_shipments_table.php",
    "2026_06_19_110008_create_purchase_logistics_table.php",
    "2026_06_19_110009_create_purchase_approvals_table.php",
]
for name in files:
    local = os.path.join(LOCAL_BASE, "database/migrations", name)
    sftp.put(local, "/tmp/" + name)
    print(f"  ✓ put {name}")
sftp.close()

# 4) cp 过去
for name in files:
    run(f"sudo -n cp -f /tmp/{name} /var/www/oa-api/database/migrations/{name}")

# 5) 验证 172 上文件名
rc, o, e = run("ls -la /var/www/oa-api/database/migrations/2026_06_19_11000*_create_purchase*.php")
print(f"\n  172 migration 目录:\n{o}")

# 6) 跑 migrate
print("\n=== migrate --force ===")
rc, o, e = run("cd /var/www/oa-api && sudo -n -u www-data php artisan migrate --force 2>&1")
print(f"rc={rc}")
print(o)
print(e)

ssh.close()
