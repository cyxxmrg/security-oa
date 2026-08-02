#!/bin/bash
# 手动备份: pg_dump + 打包 storage
set -e
cd "$(dirname "$0")/.."
. ./.env

STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR="data/backups"
mkdir -p "$BACKUP_DIR"

echo "📦 备份 PG ..."
docker compose exec -T db pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" --no-owner --no-acl 2>/dev/null \
  | gzip > "$BACKUP_DIR/pg-${STAMP}.sql.gz"

echo "📦 备份 storage ..."
tar -czf "$BACKUP_DIR/storage-${STAMP}.tar.gz" -C data storage/ 2>/dev/null || true

# 保留 7 天
find "$BACKUP_DIR" -name "pg-*.sql.gz"       -mtime +7 -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "storage-*.tar.gz"  -mtime +7 -delete 2>/dev/null || true

echo "✅ 备份完成: $BACKUP_DIR/pg-${STAMP}.sql.gz"
ls -lh "$BACKUP_DIR/" | tail -10
