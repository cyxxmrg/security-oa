#!/bin/bash
# 导入老 PG 数据 (sql.gz → 新容器 db)
# 用法: bash scripts/import-data.sh exports/117-pg-20260627.sql.gz
set -e
cd "$(dirname "$0")/.."

GZ_FILE=$1
[ -z "$GZ_FILE" ] && { echo "用法: $0 <sql.gz>"; exit 1; }
[ -f "$GZ_FILE" ] || { echo "❌ $GZ_FILE 不存在"; exit 1; }

# 加载 .env
. ./.env 2>/dev/null || { echo "❌ .env 不存在, 先 bash deploy.sh"; exit 1; }

# 等 db 起来
echo "⏳ 等 db 起来 ..."
for i in $(seq 1 30); do
  if docker compose exec -T db pg_isready -U "$DB_USERNAME" -d "$DB_DATABASE" 2>/dev/null; then
    break
  fi
  sleep 2
done

# 清空再灌入 (drop + createdb)
echo "🗑️  清空目标库 (保留 docker volume)..."
docker compose exec -T db psql -U "$DB_USERNAME" -d postgres -c \
  "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='$DB_DATABASE' AND pid<>pg_backend_pid();" 2>/dev/null || true
docker compose exec -T db dropdb -U "$DB_USERNAME" --if-exists "$DB_DATABASE" 2>/dev/null || true
docker compose exec -T db createdb -U "$DB_USERNAME" "$DB_DATABASE" 2>/dev/null || true

# 解压灌入
echo "📥 导入 $GZ_FILE ..."
gunzip -c "$GZ_FILE" | docker compose exec -T db psql \
  -U "$DB_USERNAME" -d "$DB_DATABASE" \
  --set ON_ERROR_STOP=0 2>&1 | tail -30 | tee data/backups/import-$(date +%Y%m%d-%H%M%S).log

# 重启 api 让它跑 migrate (补齐 deleted_at 等列 + view)
echo "🔄 重启 api 让 entrypoint 修复 view + 列..."
docker compose restart api

# 等 api healthy
echo "  ⏳ 等 api healthy ..."
for i in $(seq 1 30); do
  if docker compose ps api 2>/dev/null | grep -q "(healthy)"; then
    break
  fi
  sleep 2
done

# 跑 PermissionRoleSeeder 重建 role_has_permissions view
echo "🌱 跑 PermissionRoleSeeder ..."
docker compose exec -T api php artisan db:seed --class=PermissionRoleSeeder --force 2>&1 | tail -3 || echo "  ⚠️  seeder 失败, 但数据已在"

echo "✅ 导入完成"
