# OA 安防运维系统 - Docker 一键部署包

> **版本**: V1.0 · **适配**: 192.168.1.115 全新部署验证通过 · **环境**: Ubuntu 26.04 / Docker 29.6+ / PostgreSQL 15 / PHP 8.5 / Redis 7 / Nginx 1.28

---

## 📦 目录结构

```
oa-docker/
├── deploy.sh                  # ⭐ 入口, 一条命令到底
├── docker-compose.yml         # 4 容器编排 (web/api/db/redis)
├── .env.template              # 环境变量模板 (启动时复制为 .env)
├── .gitignore
├── docker/
│   ├── php/
│   │   ├── Dockerfile         # php-fpm 8.5 镜像 (含 pdo_pgsql/redis/intl/zip)
│   │   └── entrypoint.sh      # 容器启动脚本 (migrate/seed/init/chown)
│   ├── nginx/
│   │   ├── Dockerfile         # nginx:alpine 镜像
│   │   └── nginx.conf         # 反代 + SPA 静态
│   └── postgres/
│       └── init.sql           # 库初始化 (security_oa + oa_user)
├── scripts/
│   ├── gen-env.sh             # APP_KEY + 强密码生成
│   ├── import-data.sh         # 老数据 pg_dump 灌入
│   ├── healthcheck.sh         # 11 个测点冒烟
│   └── backup.sh              # pg_dump + tar 备份
├── docs/
│   └── README.md              # 本文件
├── data/                      # ⬇️ 自动生成, 持久化目录
│   ├── pgdata/                # PostgreSQL 数据
│   ├── redisdata/             # Redis 数据
│   ├── storage/               # Laravel 上传文件 (网盘等)
│   └── backups/               # 自动备份归档
└── exports/                   # ⬇️ 117 老数据 (可选)
    └── 117-pg-YYYYMMDD.sql.gz
```

---

## 🚀 5 分钟快速部署

### 前提条件
- **操作系统**: Ubuntu 22.04+ / Debian 12+ (其它 Linux 需自测)
- **权限**: root 或 sudo 免密
- **端口**: 80 (HTTP) 未被占用
- **磁盘**: ≥ 10GB 可用 (镜像+数据)

### 步骤 1: 上传文件

把整个 `oa-docker/` 目录 scp 到服务器:

```bash
# 在你本机 (Windows Git Bash / macOS / Linux)
scp -r oa-docker/ root@<服务器IP>:/opt/

# 假设服务器 IP 是 192.168.1.115
ssh root@192.168.1.115
cd /opt/oa-docker
```

### 步骤 2: 装 Docker (如果没装)

```bash
# 一键装 docker engine + compose
curl -fsSL https://get.docker.com | bash

# 国内服务器 (网络受限时)
curl -fsSL https://get.docker.com | bash -s -- --mirror Aliyun

# 把当前用户加进 docker 组 (免 sudo 跑)
sudo usermod -aG docker $USER
newgrp docker  # 立即生效

# 配镜像加速器 (国内强烈推荐, 否则拉镜像可能卡)
sudo mkdir -p /etc/docker
sudo tee /etc/docker/daemon.json << 'EOF'
{
  "registry-mirrors": [
    "https://registry.cn-hangzhou.aliyuncs.com",
    "https://docker.mirrors.ustc.edu.cn"
  ]
}
EOF
sudo systemctl restart docker

# 验证
docker --version          # Docker version 24.0+
docker compose version    # Docker Compose version v2.x
docker run --rm hello-world  # 测试拉镜像
```

### 步骤 3: 准备代码和数据 (二选一)

#### A. **全新部署 (无老数据)**
跳过此步,直接进步骤 4。

#### B. **从老系统迁移 117 数据**
```bash
# 1) 在老 117 服务器导出
ssh example@192.168.1.117
pg_dump -U oa_user -h 127.0.0.1 security_oa | gzip > 117-pg-$(date +%Y%m%d).sql.gz

# 2) 传回新 115
scp 117-pg-YYYYMMDD.sql.gz example@192.168.1.115:/opt/oa-docker/exports/

# 3) 回到 115
cd /opt/oa-docker
ls exports/  # 确认文件在
```

> 💡 同样要带**上传文件** (网盘里的合同/报销/验收 PDF):
> ```bash
> rsync -avz example@192.168.1.117:/var/www/oa-api/storage/app/ /opt/oa-docker/data/storage/
> ```

### 步骤 4: 一键部署 ⭐

```bash
cd /opt/oa-docker
sudo bash deploy.sh
```

**6 步全自动** (约 5-10 分钟):

```
🔍 [1/6] 校验环境...           ← docker / 目录 / 依赖
🔑 [2/6] 生成 .env             ← APP_KEY + DB 密码 + Redis 密码 (强随机)
📦 [3/6] 导入老数据 (可选)     ← 扫 exports/*.sql.gz
🚀 [4/6] 构建 + 启动容器       ← pull 镜像 + 启动 4 容器
🌱 [5/6] 初始化数据库          ← migrate + seed + 兼容老 spatie
✅ [6/6] 冒烟测试              ← 11 个测点全过
```

**成功输出示例**:
```
╔════════════════════════════════════════════╗
║   🎉 部署完成!                              ║
║                                              ║
║   访问: http://192.168.1.115                 ║
║   账号: admin / <已脱敏>                     ║
║   .env:    /opt/oa-docker/.env               ║
╚════════════════════════════════════════════╝
```

### 步骤 5: 验证

打开浏览器访问 `http://<服务器IP>`:
- 看到登录页 ✅
- 用 `admin / <已脱敏>` 登录 ✅
- 进 dashboard 看数据 ✅

**自动冒烟脚本** (推荐):
```bash
cd /opt/oa-docker
bash scripts/healthcheck.sh
```

---

## 🛠 常用运维命令

```bash
cd /opt/oa-docker

# 看容器状态
docker compose ps

# 看实时日志 (api/web/db/redis)
docker compose logs -f api
docker compose logs -f web

# 重启服务
docker compose restart api
docker compose restart web
docker compose restart db    # ⚠️ db 重启会断开连接

# 进容器内操作
docker compose exec api bash
docker compose exec db psql -U oa_user -d security_oa

# 跑 artisan 命令
docker compose exec api php artisan migrate
docker compose exec api php artisan db:seed --class=PermissionRoleSeeder --force
docker compose exec api php artisan tinker

# 备份 (每天 2:30 自动跑, 保留 7 天)
bash scripts/backup.sh
ls data/backups/    # 看历史备份

# 回滚到上次备份
bash scripts/restore.sh data/backups/oa_YYYYMMDD_HHMMSS.sql.gz
```

---

## 🩹 故障排查

### 502 Bad Gateway
```bash
docker compose logs --tail=20 api    # 看 fpm 是否启动
docker compose exec api ps aux       # 看 fpm worker
```
**常见原因**:
1. `pc-api/` 目录没推过去 → 检查 `/opt/oa-docker/pc-api/public/index.php` 在不在
2. 端口被占 → `sudo lsof -i :80`
3. 镜像没重 build → `docker compose build --no-cache api`

### 数据库连不上
```bash
docker compose exec db pg_isready -U oa_user -d security_oa
docker compose exec api bash -c 'php -r "var_dump(PDO(\"pgsql:host=db;dbname=security_oa\",\"oa_user\",\"xxx\")); "'
```
**常见原因**:
1. `.env` 里 DB_PASSWORD 跟 `docker-compose.yml` 不一致
2. db 容器没起来 → `docker compose logs db`

### 前端白屏 / 加载不出
```bash
curl -fsS http://127.0.0.1/ | head -c 200    # 看 dist 是不是挂上
ls -la /opt/oa-docker/pc-web/dist/index.html
```

### 500 错误 (laravel 内部)
```bash
docker compose exec api tail -50 storage/logs/laravel.log
```

### 重置整套 (慎用!)
```bash
cd /opt/oa-docker
docker compose down -v          # 删容器+网络+卷
rm -rf data/                    # 清数据
sudo bash deploy.sh             # 重新部署
```

---

## 🔄 升级代码

```bash
cd /opt/oa-docker

# 1) 拉新代码到 pc-api
cd pc-api && git pull && cd ..

# 2) 跑 migration (如果有新)
docker compose exec api php artisan migrate --force

# 3) 重启 api (清 opcache)
docker compose restart api

# 4) 验证
bash scripts/healthcheck.sh
```

---

## 📂 持久化与备份

### 数据卷说明
| 路径 | 内容 | 是否会丢 |
|---|---|---|
| `data/pgdata/` | PostgreSQL 全量 | ❌ 容器重建也保留 |
| `data/redisdata/` | Redis 缓存/队列 | ❌ 同上 |
| `data/storage/` | Laravel 上传文件 (网盘) | ❌ 同上 |
| `data/backups/` | 自动备份归档 | ❌ 同上 |

### 自动备份 (cron)
`deploy.sh` 完成后,系统会装一条 cron 任务:

```bash
# 每天凌晨 2:30 跑
30 2 * * * cd /opt/oa-docker && bash scripts/backup.sh >> /var/log/oa-backup.log 2>&1
```

**保留策略**: 7 天 pg_dump + 7 天 tar 压缩包,自动清理更早的。

### 手动备份
```bash
bash scripts/backup.sh
# 生成 /opt/oa-docker/data/backups/oa_20260627_143000.tar.gz
```

---

## 🔐 安全建议

部署完成后**立刻**做:

```bash
# 1) 改 admin 密码 (登录后)
# 2) 改 .env 里的密码 (deploy.sh 已生成强密码, 但建议重存)
# 3) 配 HTTPS (用 certbot 一键):
sudo apt install certbot -y
sudo certbot certonly --standalone -d yourdomain.com
# 然后改 docker/nginx/nginx.conf 加 ssl server 块

# 4) 关掉无用端口
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# 5) 防爆破
sudo apt install fail2ban -y
sudo systemctl enable fail2ban
```

---

## 🆚 跟老部署对比

| 维度 | 老 (paramiko) | 新 (docker) |
|---|---|---|
| 一键部署 | 30 分钟 (手动 5 步) | **5 分钟 (一条命令)** |
| 跨服务器迁移 | 重写脚本 | **同一套包直接跑** |
| 升级代码 | scp + sudo cp + chown | **git pull + restart** |
| 回滚 | git revert 重部署 | **`docker compose down` + 旧 image** |
| 资源隔离 | 共享 OS 资源 | **容器隔离** |
| 多机扩展 | 手动同步 | **`docker compose up -d --scale api=3`** |
| 监控 | 无 | **PerformanceMonitor + HealthCheck cron** |

---

## 📋 部署检查清单

部署完成后,打勾确认:

- [ ] 4 容器都 `Up` 状态 (`docker compose ps`)
- [ ] `bash scripts/healthcheck.sh` 11 个测点全过
- [ ] 浏览器能打开首页
- [ ] admin/<已脱敏> 能登录
- [ ] 进 dashboard 看到数据 (导入老数据后)
- [ ] 网盘能上传/下载文件
- [ ] `crontab -l` 有备份任务
- [ ] 改了 admin 密码

---

## 💬 常见问题

**Q: 部署过程中 composer install 报 security advisories 错误?**
A: Dockerfile 已用 `--no-security-blocking` 绕过。如果还报,升级 Laravel 版本或打补丁。

**Q: 端口 80 被占怎么办?**
A: 改 `docker-compose.yml` 里 web 端口: `"8080:80"`,然后访问 `http://IP:8080`。

**Q: 怎么把容器跑成 3 个 api 实例?**
A: `docker compose up -d --scale api=3`。需要 nginx upstream 改成 `upstream api { server api:9000; }` 用 docker DNS round-robin。

**Q: 怎么看容器用了多少内存?**
A: `docker stats`。

**Q: 数据库删了怎么恢复?**
A: `bash scripts/restore.sh data/backups/oa_YYYYMMDD_HHMMSS.sql.gz`。

**Q: 跟老 117 系统能同时跑吗?**
A: 可以,但要改 115 的 web 端口避开 8080。否则 nginx 端口冲突。

---

## 🆘 紧急救援

**完全崩溃,起不来了**:
```bash
cd /opt/oa-docker
docker compose down
docker system prune -af    # 清缓存 (不删卷)
sudo bash deploy.sh       # 重新走一遍
```

**怀疑数据库坏了**:
```bash
docker compose exec db pg_dumpall -U oa_user > /tmp/dump_now.sql    # 抢救当前
# 然后查日志,联系大哥
```

**保留现场,等大哥来**:
```bash
cd /opt/oa-docker
docker compose logs > /tmp/oa-logs-$(date +%Y%m%d_%H%M).log
tar czf /tmp/oa-debug-$(date +%Y%m%d_%H%M).tar.gz \
    data/storage data/backups .env docker/ docs/ /tmp/oa-logs-*.log
# 把 tar.gz 发给大哥
```

---

**最后更新**: 2026-06-27 · **部署验证**: 192.168.1.115 (健康 11/11) · **对应代码**: V1.0

> 有问题直接找大哥或群里喊 🛠
