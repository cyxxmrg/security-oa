#!/usr/bin/env bash
# =============================================================
#  OA 卸载脚本 (危险! 会删数据库和应用文件)
#  用法: sudo bash uninstall.sh
# =============================================================
set -e

R='\033[1;31m'; Y='\033[1;33m'; N='\033[0m'
echo -e "${R}!! 这是卸载脚本, 会删数据库 / 网站文件 / cron !!${N}"
echo -e "${Y}备份在 /var/backups/oa/pg/ 下, 不会动${N}"
echo ""
read -p "确认要卸载吗? 输入 YES 继续: " confirm
[[ "$confirm" == "YES" ]] || { echo "取消"; exit 0; }

# 1. 停服务
systemctl disable --now oa-cron 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

# 2. 删 nginx
rm -f /etc/nginx/sites-enabled/oa /etc/nginx/sites-enabled/oa-api
rm -f /etc/nginx/sites-available/oa /etc/nginx/sites-available/oa-api
systemctl reload nginx 2>/dev/null || true

# 3. 删应用
rm -rf /var/www/oa-api /var/www/oa-web

# 4. 删 PG 数据库 (警告!)
echo -e "${R}删数据库? (会丢所有数据, 备份在 /var/backups/oa/pg/)${N}"
read -p "输入 DROP_DB 继续: " dbconfirm
if [[ "$dbconfirm" == "DROP_DB" ]]; then
    sudo -u postgres psql -c "DROP DATABASE IF EXISTS security_oa;" 2>/dev/null
    sudo -u postgres psql -c "DROP USER IF EXISTS oa_user;" 2>/dev/null
    echo "数据库已删"
else
    echo "保留数据库"
fi

# 5. 删 cron
rm -f /etc/cron.d/oa-backup /etc/cron.d/oa-healthcheck
rm -rf /opt/oa

# 6. PHP-FPM pool
rm -f /etc/php/8.3/fpm/pool.d/oa.conf
systemctl restart php8.3-fpm

# 7. UFW
ufw delete allow 8081/tcp 2>/dev/null || true

echo ""
echo "卸载完成 (但 PG/Redis/Nginx/Composer 包装未删, 可手动 apt remove)"
