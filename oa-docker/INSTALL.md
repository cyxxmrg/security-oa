# OA 安防运维系统 · 一键安装指南

> 面向运维/客户. 5 分钟跑完, 一条命令到底.

---

## 前提

| 项 | 要求 |
|---|---|
| 系统 | Ubuntu 22.04+ / Debian 12+ |
| 权限 | root 或 sudo 免密 |
| 端口 | 80 未占用 |
| 磁盘 | ≥ 10GB |
| 网络 | 能拉 docker.io / 阿里云镜像 |

---

## 三步装机

### 第 1 步: 上传并解包

把 `oa-docker.zip` 传到服务器, 解压:

```bash
unzip oa-docker.zip -d /opt/
cd /opt/oa-docker
```

### 第 2 步: 放代码 (二选一)

**A. 用默认自带的代码 (推荐先测)**

```bash
# 目录里需有 pc-api/ 和 pc-web/dist/
# 缺失请从源码仓库补齐
ls pc-api/composer.json pc-web/dist/index.html
```

**B. 从老服务器迁数据 (可选)**

```bash
# 在老服务器 117 上导出
ssh example@117 "pg_dump -U oa_user security_oa | gzip" > exports/117-pg-$(date +%Y%m%d).sql.gz

# 把 .sql.gz 放到本目录的 exports/ 下
# deploy.sh 会自动识别并导入
```

### 第 3 步: 一条命令安装

```bash
bash deploy.sh
```

**全程约 5-10 分钟**, 期间会:
1. 检查环境 (docker / 端口 / 目录)
2. 生成 APP_KEY + 数据库强密码
3. 导入老数据 (如有)
4. 构建 4 个镜像 (php / nginx / postgres / redis)
5. 启动容器, 自动跑 migrate / seed / 权限 / 网盘初始化
6. 冒烟测试 12 个端点

---

## 安装完能用吗?

跑完看到:

```
🎉 部署完成!
访问: http://<服务器IP>
账号: admin / <已脱敏> (首次登录后改密码)
```

就成功了. 浏览器打开 `http://服务器IP` 用 admin/<已脱敏> 登录.

---

## 常用命令

```bash
cd /opt/oa-docker

docker compose ps                # 看容器状态
docker compose logs -f api       # 看后端日志
docker compose restart api       # 重启后端
docker compose down              # 停所有
docker compose up -d             # 启动所有

bash scripts/healthcheck.sh      # 12 端点冒烟
bash scripts/backup.sh           # 手动备份 (pg + storage)
```

---

## 数据在哪

```
/opt/oa-docker/
├── data/pgdata/      # PostgreSQL 数据库 (迁移拷走)
├── data/redisdata/   # Redis 缓存 (可重建)
├── data/storage/     # 用户上传文件 (合同/报销/验收 PDF) ⭐ 必须备份
└── data/backups/     # 自动备份归档
```

**升级前先备份**, 跨机迁移只拷 `data/pgdata/` + `data/storage/`.

---

## 出问题怎么办

1. **跑一遍冒烟**: `bash scripts/healthcheck.sh` 看哪步红
2. **看后端日志**: `docker compose logs --tail=100 api`
3. **看 nginx 日志**: `docker compose logs --tail=100 web`
4. **重启某个**: `docker compose restart api`
5. **完全重置**: `docker compose down -v && bash deploy.sh` ⚠️ 数据清空

---

## FAQ

**Q: 80 端口被占?**
改 `.env` 里 `WEB_PORT=8080`, 然后 `docker compose up -d`.

**Q: 启动失败 composer install 报错?**
容器内 `docker compose exec api bash`, 看 `vendor/` 是否完整. 一般重 build 即可: `docker compose build --no-cache api`.

**Q: 迁移老数据后登录 500?**
大概率是 spatie 旧表不兼容. 在 `db` 容器内执行:
```sql
CREATE TABLE IF NOT EXISTS role_has_permissions (permission_id BIGINT, model_type VARCHAR(125), model_id BIGINT);
CREATE TABLE IF NOT EXISTS role_has_users (role_id BIGINT, user_id BIGINT);
```

**Q: 怎么改前端?**
本地改 `pc-web/`, `npm run build` 后把 `dist/` 推上去:
```bash
rsync -av pc-web/dist/ example@<IP>:/opt/oa-docker/pc-web/dist/
```

---

**详细文档**: `docs/README.md`
**版本**: V1.0 (2026-06-27)