#!/bin/bash
# ============================================
# OA Docker 一键全自动部署
# 用法: bash deploy.sh [--reset]
#   --reset  先清掉 data/ 和镜像, 从头重建
# ============================================
set -e

# 颜色
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${GREEN}[$(date +%H:%M:%S)]${NC} $*"; }
warn() { echo -e "${YELLOW}[$(date +%H:%M:%S)]${NC} $*"; }
err()  { echo -e "${RED}[$(date +%H:%M:%S)] ❌${NC} $*"; }

# 切到脚本目录
cd "$(dirname "$0")"

# ========================================
# 0. 解析参数
# ========================================
RESET_MODE=0
for arg in "$@"; do
  case $arg in
    --reset) RESET_MODE=1 ;;
    *) err "未知参数: $arg"; exit 1 ;;
  esac
done

# ========================================
# 1. 校验
# ========================================
log "1/8 校验环境..."
command -v docker >/dev/null 2>&1 || { err "缺 docker, 先装: curl -fsSL https://get.docker.com | bash"; exit 1; }
docker compose version >/dev/null 2>&1 || { err "缺 docker compose v2"; exit 1; }
[ -d pc-api ] || { err "缺 pc-api/ 目录"; exit 1; }
[ -d pc-web/dist ] || { err "缺 pc-web/dist/ 目录, 先 cd pc-web && npm i && npm run build"; exit 1; }
[ -f pc-api/.env.example ] || { err "缺 pc-api/.env.example"; exit 1; }
log "✅ 环境 OK"

# ========================================
# 2. 生成 .env
# ========================================
log "2/8 准备 .env..."
if [ ! -f .env ]; then
  cp .env.template .env
  log "已复制 .env.template → .env"
fi

if grep -q "CHANGEME" .env; then
  log "生成随机密钥 (APP_KEY / DB_PASSWORD / REDIS_PASSWORD) ..."
  bash scripts/gen-env.sh
fi
. ./.env
log "✅ .env 就绪 (DB=$DB_DATABASE, WEB_PORT=$WEB_PORT)"

# ========================================
# 3. 创建持久化目录
# ========================================
log "3/8 创建持久化目录..."
mkdir -p data/pgdata data/redisdata data/storage data/backups exports
chmod 777 data/storage data/backups
log "✅ data/ 就绪"

# ========================================
# --reset: 删旧容器 + 镜像 + data
# ========================================
if [ "$RESET_MODE" = "1" ]; then
  log "🔄 --reset 模式: 清旧容器/镜像/data ..."
  docker compose down --remove-orphans 2>&1 | tail -3 || true
  docker compose down --rmi all 2>&1 | tail -3 || true
  rm -rf data/pgdata data/redisdata
  log "✅ 已清空"
fi

# ========================================
# 4. 老数据导入 (exports/*.sql.gz)
# ========================================
log "4/8 检测老数据 ..."
if ls exports/*.sql.gz 1>/dev/null 2>&1; then
  HAS_OLD_DATA=1
  warn "发现 exports/ 有 sql.gz 文件 (将先导老数据)"
else
  HAS_OLD_DATA=0
  log "  无老数据, 进入全新安装模式"
fi

# ========================================
# 5. docker compose 启动
# ========================================
log "5/8 启动 db + redis ..."
docker compose up -d db redis
log "  ⏳ 等 db/redis healthy (最长 60s)..."
for i in $(seq 1 30); do
  DB_OK=$(docker compose ps db --format '{{.State}}' 2>/dev/null | grep -c "healthy" || echo 0)
  RD_OK=$(docker compose ps redis --format '{{.State}}' 2>/dev/null | grep -c "healthy" || echo 0)
  if [ "$DB_OK" = "1" ] && [ "$RD_OK" = "1" ]; then
    log "  ✅ db + redis healthy"
    break
  fi
  sleep 2
done

# 老数据导入 (db 起来后)
if [ "$HAS_OLD_DATA" = "1" ]; then
  for f in exports/*.sql.gz; do
    log "  → 导入 $f"
    bash scripts/import-data.sh "$f" || warn "  导入失败, 继续"
  done
  OA_FRESH_INSTALL=0
else
  OA_FRESH_INSTALL=1
fi

# 把 OA_FRESH_INSTALL 注入 .env (容器会读)
if grep -q "^OA_FRESH_INSTALL=" .env; then
  sed -i "s|^OA_FRESH_INSTALL=.*|OA_FRESH_INSTALL=$OA_FRESH_INSTALL|" .env
else
  echo "OA_FRESH_INSTALL=$OA_FRESH_INSTALL" >> .env
fi

# 启动 api + web
log "  启动 api + web ..."
docker compose up -d api web

log "  ⏳ 等 api 起来 (最长 360s, 首次启动 composer install + migrate)..."
WAITED=0
LAST_STATE=""
for i in $(seq 1 180); do
  STATE=$(docker compose ps api --format '{{.State}}' 2>/dev/null | head -1)
  # 检测 fpm 真的在监听 9000 (2328 = 9000 hex)
  if [ "$STATE" = "running" ]; then
    if docker compose exec -T api sh -c 'cat /proc/net/tcp 2>/dev/null | grep -i 2328' 2>/dev/null | grep -q .; then
      log "  ✅ api running + 监听 9000 (耗时 ${WAITED}s)"
      break
    fi
  fi
  if [ "$STATE" != "$LAST_STATE" ]; then
    log "  ... api 状态: ${STATE:-starting}"
    LAST_STATE="$STATE"
  fi
  # 重启状态不退出, 持续等 (entrypoint 在跑)
  sleep 2
  WAITED=$((WAITED+2))
  if [ $((WAITED % 30)) -eq 0 ] && [ $WAITED -gt 0 ]; then
    log "  ... 已等 ${WAITED}s"
  fi
done

if [ $WAITED -ge 360 ]; then
  err "api 启动超时 (360s)"
  log "请检查: docker compose logs --tail=50 api"
  exit 1
fi

# 再保险 15s 让 entrypoint 跑完最后步骤
sleep 15

# ========================================
# 6. 数据库初始化 + 兜底 schema
# ========================================
log "6/8 数据库初始化..."

# 跑一遍 migrate (幂等)
docker compose exec -T api php artisan migrate --force 2>&1 | tail -5 || warn "migrate 失败"

# 全新安装 + seed
if [ "$OA_FRESH_INSTALL" = "1" ]; then
  log "  首次安装, 跑 seed ..."
  docker compose exec -T api php artisan db:seed --class=DatabaseSeeder --force 2>&1 | tail -3 || warn "seeder 失败"
  docker compose exec -T api php artisan db:seed --class=PermissionRoleSeeder --force 2>&1 | tail -3 || warn "permission seeder 失败"
fi

# 兜底: disk-init (老数据无 root 目录, 补建)
docker compose exec -T api php artisan oa:disk-init 2>&1 | tail -3 || warn "disk-init 失败"

# ========================================
# 7. 缓存
# ========================================
log "7/8 缓存..."
docker compose exec -T api php artisan view:clear 2>&1 | tail -2 || true
docker compose exec -T api php artisan storage:link 2>&1 | tail -2 || true
# config:cache + route:cache 默认关闭 (有 opcache 兼容问题)
log "  跳过 config:cache (Laravel 11 + fpm 兼容问题)"

# ========================================
# 8. 冒烟测试
# ========================================
log "8/8 冒烟测试..."
bash scripts/healthcheck.sh

# ========================================
# 收尾
# ========================================
HOST_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "127.0.0.1")

cat <<EOF

╔════════════════════════════════════════════════════╗
║                                                    ║
║   🎉  OA Docker 部署完成!                          ║
║                                                    ║
║   访问地址:  http://${HOST_IP}:${WEB_PORT}            ║
║                                                    ║
║   默认账号:  admin / <已脱敏>                      ║
║   (首次登录后请立即修改密码)                         ║
║                                                    ║
║   常用命令:                                         ║
║     docker compose ps                 看状态        ║
║     docker compose logs -f api        看 API 日志   ║
║     docker compose logs -f web        看 nginx 日志 ║
║     docker compose restart api        重启后端      ║
║     bash scripts/backup.sh            手动备份      ║
║     bash scripts/healthcheck.sh       状态检查      ║
║                                                    ║
║   出问题重来:                                       ║
║     bash reset.sh                    一键重置       ║
║                                                    ║
║   持久化:                                          ║
║     data/pgdata/      数据库                        ║
║     data/redisdata/   缓存                          ║
║     data/storage/     上传文件                      ║
║     data/backups/     自动备份                      ║
║                                                    ║
╚════════════════════════════════════════════════════╝
EOF