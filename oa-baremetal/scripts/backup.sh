#!/usr/bin/env bash
# =============================================================
#  OA 每日自动备份: PG dump + storage tar
#  保留 7 天, 老备份自动删
#  cron: 0 2 * * *
# =============================================================
set -euo pipefail

PG_USER="oa_user"
PG_PASS="oa_pg_pwd_782997781"   # 跟 .env 一致
PG_DB="security_oa"
BACKUP_DIR="/var/backups/oa/pg"
STORAGE_DIR="/var/www/oa-api/storage/app"

mkdir -p "$BACKUP_DIR"
cd /tmp

DATE=$(date +%Y%m%d_%H%M%S)

# 1) PG dump
export PGPASSWORD="$PG_PASS"
pg_dump -h 127.0.0.1 -U "$PG_USER" -d "$PG_DB" \
    --no-owner --no-privileges \
    | gzip -9 > "$BACKUP_DIR/pg_${DATE}.sql.gz"

# 2) storage tar (用户上传文件)
[[ -d "$STORAGE_DIR" ]] && tar czf "$BACKUP_DIR/storage_${DATE}.tar.gz" -C /var/www/oa-api storage/app

# 3) 删 7 天前
find "$BACKUP_DIR" -name "pg_*.sql.gz"       -mtime +7 -delete
find "$BACKUP_DIR" -name "storage_*.tar.gz"  -mtime +7 -delete

# 4) 报告
echo "[$(date)] backup done → $BACKUP_DIR"
ls -la "$BACKUP_DIR" | tail -5
