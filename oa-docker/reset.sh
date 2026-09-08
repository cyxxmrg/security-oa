#!/bin/bash
# ============================================
# OA Docker 一键重置 (清干净重来)
# 用法: bash reset.sh [--keep-data]
#   --keep-data  保留 data/pgdata 和 data/storage (数据不丢)
# ============================================
set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${GREEN}[$(date +%H:%M:%S)]${NC} $*"; }
warn() { echo -e "${YELLOW}[$(date +%H:%M:%S)]${NC} $*"; }
err()  { echo -e "${RED}[$(date +%H:%M:%S)] ❌${NC} $*"; }

cd "$(dirname "$0")"

KEEP_DATA=0
for arg in "$@"; do
  case $arg in
    --keep-data) KEEP_DATA=1 ;;
    *) err "未知参数: $arg"; exit 1 ;;
  esac
done

warn "⚠️  即将重置 OA Docker 环境"
if [ "$KEEP_DATA" = "0" ]; then
  warn "  - data/pgdata, data/storage, data/redisdata 都会被删"
  warn "  - 所有 docker 镜像也会被删"
  echo
  read -p "确认继续? (yes/no): " CONFIRM
  [ "$CONFIRM" = "yes" ] || { log "已取消"; exit 0; }
fi

log "🔄 停容器 ..."
docker compose down --remove-orphans 2>&1 | tail -3 || true

log "🗑️  删镜像 ..."
docker compose down --rmi all 2>&1 | tail -3 || true

if [ "$KEEP_DATA" = "0" ]; then
  log "🗑️  清 data/ ..."
  rm -rf data/pgdata data/redisdata data/backups/*.log
  log "  保留: data/storage/ (上传文件)"
fi

log "🧹 清理残留 (网络/匿名卷) ..."
docker network prune -f 2>&1 | tail -3 || true
docker volume prune -f 2>&1 | tail -3 || true

log "✅ 重置完成"
echo
log "下一步: bash deploy.sh"