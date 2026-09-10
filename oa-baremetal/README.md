# OA System 一键部署包 (Ubuntu 24.04 / 26.04 LTS)

> **适合场景**: 全新服务器 + 空数据库, 不用 Docker, 原生 PHP + PG + Redis + Nginx
> **耗时**: 5-10 分钟 (含 composer install)
> **结果**: 浏览器开 `http://服务器IP/` 就能登录 admin/<已脱敏>

---

## 📦 包结构

```
oa-baremetal/
├── deploy.sh                  # 一键主脚本 (ubuntu 上跑这个)
├── uninstall.sh               # 卸载 (危险)
├── VERSION                    # 当前版本号
├── config/                    # 配置文件
├── scripts/
│   ├── build-package.ps1      # Windows 端: 打整个部署包
│   ├── backup.sh              # 每日 PG+storage 自动备份
│   └── healthcheck.sh         # 5 分钟健康检查
└── docs/
    ├── POST_INSTALL.md        # 部署后该做什么
    └── TROUBLESHOOTING.md     # 常见问题
```

---

## 🚀 5 步走

### 1. Windows 端: 打包

在 `oa-baremetal/scripts/` 里右键 `build-package.ps1` → "用 PowerShell 运行"。

它会:
- 装 `pc-api/vendor` (composer install --no-dev)
- build `pc-web` (vite build → dist/)
- 把 `pc-api/` + `pc-web-dist/` + `oa-baremetal/` 打成 `dist/oa-deploy-v1.2.8-20260101.tar.gz`

约 30-60 秒。输出:

```
✓ 部署包构建完成
  文件: dist/oa-deploy-v1.2.8-20260101.tar.gz
  大小: 45.32 MB
```

### 2. 上传到服务器

```powershell
scp dist\oa-deploy-v1.2.8-20260101.tar.gz user@192.168.1.X:/tmp/
```

### 3. 服务器端: 解压

```bash
ssh user@192.168.1.X
cd /tmp
tar xzf oa-deploy-v1.2.8-*.tar.gz
cd oa-deploy-v1.2.8-20260101
ls
# 应该看到: deploy.sh  uninstall.sh  pc-api  pc-web-dist  scripts  VERSION
```

### 4. 服务器端: 一键部署

```bash
sudo bash deploy.sh
```

跑完会看到:

```
[✓] 基础包装完
[✓] 服务全部 enabled
[✓] PG 用户/库建好 (空)
[✓] PG 连接验证通过
[✓] Redis OK
[✓] PHP-FPM pool 配好
[✓] Laravel 部署完成
[✓] 前端部署完成 (12.5M)
[✓] Nginx 配置 OK
[✓] UFW 防火墙 (22/80/8081)
[✓] fail2ban 启用
[✓] 备份 + 健康检查 cron

=========================================
  部署完成!
  前端:    http://192.168.1.X/
  API:     http://192.168.1.X:8081/api
  默认账号: admin / <已脱敏>
           manager / 123456
           system / System@123
=========================================
```

### 5. 浏览器访问

打开 `http://192.168.1.X/`, 用 `admin` / `<已脱敏>` 登录。

---

## ⚙️ 部署时改什么

`deploy.sh` 顶部 3 个密码/配置, **一定要改**!

```bash
PG_USER="oa_user"
PG_PASS="oa_pg_pwd_782997781"        # ← 改!
REDIS_PASS="oa_redis_pwd_change_me"  # ← 改!
```

或者部署完手动改:

```bash
# 改 PG 密码
sudo -u postgres psql -c "ALTER USER oa_user WITH PASSWORD '你的新密码';"
# 同步 .env
sudo nano /var/www/oa-api/.env   # 改 DB_PASSWORD + REDIS_PASSWORD
sudo systemctl restart php8.5-fpm

# 改 Redis 密码
sudo sed -i 's/^requirepass .*/requirepass 你的新密码/' /etc/redis/redis.conf
sudo systemctl restart redis-server
```

---

## 📋 部署后必做

详见 `docs/POST_INSTALL.md`:

1. 改默认账号密码 (admin/manager/system/finance/user)
2. 改 DB / Redis 密码
3. 配 HTTPS (Let's Encrypt)
4. 配防火墙 (22/80/443)
5. 验证每日自动备份 (`ls /var/backups/oa/pg/`)

---

## 🔄 日常更新 (升级到新版本)

```bash
# Windows 端
# 1. 拉新代码
git pull
# 2. 重新打部署包
cd oa-baremetal/scripts
powershell -ExecutionPolicy Bypass -File build-package.ps1

# 上传 + 解压
scp dist\oa-deploy-v1.2.9-*.tar.gz user@服务器:/tmp/
ssh user@服务器
cd /tmp && tar xzf oa-deploy-v1.2.9-*.tar.gz
cd oa-deploy-v1.2.9-*

# 一键升级
sudo bash deploy.sh
```

---

## ❌ 卸载

```bash
sudo bash uninstall.sh
# 输入 YES 删应用
# 输入 DROP_DB 删数据库 (可选)
```

---

## 🐛 常见问题

详见 `docs/TROUBLESHOOTING.md`。

- 502 Bad Gateway → `systemctl status php8.5-fpm`
- 登录 500 → `tail /var/www/oa-api/storage/logs/laravel-*.log`
- 浏览器 SW 缓存旧版 → F12 → Application → Service Workers → Unregister
