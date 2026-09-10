#!/usr/bin/env bash
# =============================================================================
#  OA System 一键部署脚本 (Ubuntu 24.04/26.04 LTS)
#  适用: 全新空服务器 / 空数据库
#  用法: sudo bash deploy.sh
#  干的事:
#    1. 装 PG 15+ / Redis 7+ / PHP 8.5 / Nginx
#    2. 建数据库 security_oa (空) + 用户 oa_user
#    3. 部署 Laravel 后端 (migrate + seed)
#    4. 部署前端 dist 到 /var/www/oa-web
#    5. 配 nginx + php-fpm
#    6. 每日自动备份 + 防火墙
# =============================================================================
set -e

# -------- 颜色 --------
R='\033[1;31m'; G='\033[1;32m'; Y='\033[1;33m'; B='\033[1;34m'; N='\033[0m'
ok()   { echo -e "${G}[✓]${N} $*"; }
warn() { echo -e "${Y}[!]${N} $*"; }
err()  { echo -e "${R}[✗]${N} $*"; exit 1; }
info() { echo -e "${B}[i]${N} $*"; }

# -------- 必须 root --------
[[ $EUID -ne 0 ]] && err "请用 sudo bash deploy.sh 跑 (需要 root)"

# -------- 配置 (可改) --------
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PACKAGE_DIR="${PACKAGE_DIR:-$SCRIPT_DIR}"

PG_USER="oa_user"
PG_PASS="oa_pg_pwd_782997781"        # 改这里 ↓↓↓ 也改 .env.production
PG_DB="security_oa"

REDIS_PASS="oa_redis_pwd_change_me"

OA_WEB_ROOT="/var/www/oa-web"         # 前端 dist
OA_API_ROOT="/var/www/oa-api"         # Laravel 后端
BACKUP_DIR="/var/backups/oa/pg"

# 关键源码包路径 (相对 PACKAGE_DIR)
BACKEND_SRC="${PACKAGE_DIR}/pc-api"
FRONTEND_SRC="${PACKAGE_DIR}/pc-web-dist"

APP_VERSION=$(cat "${SCRIPT_DIR}/VERSION" 2>/dev/null || echo "unknown")

echo ""
echo "===================================================="
echo "  OA System v${APP_VERSION} 一键部署"
echo "  数据库: $PG_DB (空) | 用户: $PG_USER"
echo "  后端:   $OA_API_ROOT"
echo "  前端:   $OA_WEB_ROOT"
echo "===================================================="
echo ""

# -------- 1. 装基础包 --------
info "[1/9] 装系统包 (PG / Redis / PHP 8.5 / Nginx / Composer)..."

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq software-properties-common apt-transport-https ca-certificates curl gnupg lsb-release unzip git ufw fail2ban

# PHP 8.5 (ondrej PPA; 26.04 自带版本可能偏低)
if ! command -v php8.5 >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -qq
fi
apt-get install -y -qq \
    postgresql postgresql-contrib \
    redis-server \
    php8.5 php8.5-fpm php8.5-cli \
    php8.5-pgsql php8.5-redis php8.5-bcmath php8.5-curl \
    php8.5-mbstring php8.5-xml php8.5-zip php8.5-gd \
    php8.5-intl php8.5-imagick php8.5-opcache \
    nginx \
    composer

ok "基础包装完"

# -------- 2. 启动服务 --------
info "[2/9] 启动 PG / Redis / PHP-FPM / Nginx..."
systemctl enable --now postgresql redis-server php8.5-fpm nginx
sleep 2
ok "服务全部 enabled"

# -------- 3. 配 PG --------
info "[3/9] 配 PostgreSQL (空数据库 $PG_DB)..."

# 3.1 pg_hba.conf 改 peer→md5 (避免 peer auth 失败)
PG_HBA=$(sudo -u postgres psql -tAc "SHOW hba_file;")
if grep -qE "^local\s+all\s+all\s+peer" "$PG_HBA"; then
    sed -i.bak 's/^local\s\+all\s\+all\s\+peer/local all all md5/' "$PG_HBA"
    info "  pg_hba.conf: peer → md5"
    # 重新加载
    sudo -u postgres psql -c "SELECT pg_reload_conf();" >/dev/null
fi

# 3.2 建用户和空库 (IF NOT EXISTS 等价)
sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$PG_USER'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER $PG_USER WITH PASSWORD '$PG_PASS' CREATEDB;"

sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$PG_DB'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE $PG_DB OWNER $PG_USER ENCODING 'UTF8';"

sudo -u postgres psql -d "$PG_DB" -c "GRANT ALL PRIVILEGES ON SCHEMA public TO $PG_USER;" >/dev/null
ok "PG 用户/库建好 (空)"

# 3.3 验证连接
export PGPASSWORD="$PG_PASS"
if ! psql -h 127.0.0.1 -U "$PG_USER" -d "$PG_DB" -c "SELECT 1" >/dev/null 2>&1; then
    err "PG 连接失败, 请检查密码和 pg_hba.conf"
fi
ok "PG 连接验证通过"

# -------- 4. 配 Redis --------
info "[4/9] 配 Redis (密码鉴权)..."
REDIS_CONF="/etc/redis/redis.conf"
if ! grep -q "^requirepass $REDIS_PASS" "$REDIS_CONF" 2>/dev/null; then
    # 注释掉所有 requirepass 行, 加新的
    sed -i 's/^requirepass.*/#&/' "$REDIS_CONF"
    echo "requirepass $REDIS_PASS" >> "$REDIS_CONF"
    # 绑定 127.0.0.1 (默认就是)
    sed -i 's/^bind .*/bind 127.0.0.1 -::1/' "$REDIS_CONF"
    systemctl restart redis-server
fi
sleep 1
if ! redis-cli -a "$REDIS_PASS" --no-auth-warning ping 2>/dev/null | grep -q PONG; then
    err "Redis 验证失败"
fi
ok "Redis OK"

# -------- 5. 配 PHP-FPM pool --------
info "[5/9] 配 PHP-FPM (oa pool)..."
cat > /etc/php/8.5/fpm/pool.d/oa.conf <<EOF
[oa]
user = www-data
group = www-data
listen = /run/php/php8.5-fpm-oa.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 30
pm.start_servers = 5
pm.min_spare_servers = 2
pm.max_spare_servers = 10
pm.max_requests = 500
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 50M
php_admin_value[memory_limit] = 512M
php_admin_value[date.timezone] = Asia/Shanghai
EOF
# 删默认 pool 避免冲突
rm -f /etc/php/8.5/fpm/pool.d/www.conf
systemctl restart php8.5-fpm
sleep 1
ls -la /run/php/php8.5-fpm-oa.sock >/dev/null 2>&1 || err "PHP-FPM socket 没起"
ok "PHP-FPM pool 配好"

# -------- 6. 部署 Laravel 后端 --------
info "[6/9] 部署 Laravel 后端 → $OA_API_ROOT..."

# 6.1 准备目录
rm -rf "$OA_API_ROOT"
mkdir -p "$OA_API_ROOT" "$BACKUP_DIR"

if [[ ! -d "$BACKEND_SRC" ]]; then
    err "找不到后端源码 $BACKEND_SRC (deploy.sh 必须放在含 pc-api/ 的目录里)"
fi

# 6.2 复制
rsync -aq --delete --exclude='.env' --exclude='vendor' --exclude='node_modules' \
    "$BACKEND_SRC/" "$OA_API_ROOT/"
chown -R www-data:www-data "$OA_API_ROOT"

# 6.3 装 vendor (没打包的话)
if [[ ! -d "$OA_API_ROOT/vendor" ]]; then
    info "  装 composer 依赖 (1-2 分钟)..."
    cd "$OA_API_ROOT"
    sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction
fi

# 6.4 写 .env
sudo -u www-data tee "$OA_API_ROOT/.env" > /dev/null <<EOF
APP_NAME="OA System"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://$(hostname -I | awk '{print $1}')
APP_TIMEZONE=Asia/Shanghai
LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=$PG_DB
DB_USERNAME=$PG_USER
DB_PASSWORD=$PG_PASS

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=$REDIS_PASS
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
QUEUE_CONNECTION=redis

SANCTUM_STATEFUL_DOMAINS=$(hostname -I | awk '{print $1}'):80,localhost:3000
FILESYSTEM_DISK=public
EOF
chmod 600 "$OA_API_ROOT/.env"
chown www-data:www-data "$OA_API_ROOT/.env"

# 6.5 key:generate + 迁移 + seed
cd "$OA_API_ROOT"
sudo -u www-data php artisan key:generate --force
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --class=PermissionRoleSeeder --force
sudo -u www-data php artisan oa:disk-init || true
sudo -u www-data php artisan storage:link

# 6.6 缓存
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# 6.7 权限
chown -R www-data:www-data "$OA_API_ROOT/storage" "$OA_API_ROOT/bootstrap/cache"
ok "Laravel 部署完成"

# -------- 7. 部署前端 --------
info "[7/9] 部署前端 dist → $OA_WEB_ROOT..."

if [[ ! -d "$FRONTEND_SRC" ]]; then
    warn "  没找到 $FRONTEND_SRC, 跳过前端 (你可以后续 rsync)"
    mkdir -p "$OA_WEB_ROOT"
else
    rm -rf "$OA_WEB_ROOT"
    mkdir -p "$OA_WEB_ROOT"
    rsync -aq --delete "$FRONTEND_SRC/" "$OA_WEB_ROOT/"
    chown -R www-data:www-data "$OA_WEB_ROOT"

    # 验证 index.html
    [[ -f "$OA_WEB_ROOT/index.html" ]] || err "前端 index.html 缺失!"
    ok "前端部署完成 ($(du -sh $OA_WEB_ROOT | awk '{print $1}'))"
fi

# -------- 8. 配 Nginx --------
info "[8/9] 配 Nginx..."

cat > /etc/nginx/sites-available/oa <<'EOF'
# 前端 HTTP server
server {
    listen 80 default_server;
    server_name _;

    root /var/www/oa-web;
    index index.html;
    charset utf-8;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;
    gzip_min_length 1024;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    # API 反代到 8081
    location /api {
        proxy_pass http://127.0.0.1:8081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 60s;
        client_max_body_size 50M;
    }

    # 上传文件
    location /storage {
        alias /var/www/oa-api/storage/app/public;
        expires 30d;
        access_log off;
    }

    # SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ /\.(env|git|svn) { deny all; return 404; }
}
EOF

cat > /etc/nginx/sites-available/oa-api <<'EOF'
# API server (8081)
server {
    listen 8081;
    server_name _;

    root /var/www/oa-api/public;
    index index.php;
    charset utf-8;

    client_max_body_size 50M;

    location /storage {
        alias /var/www/oa-api/storage/app/public;
        expires 30d;
        access_log off;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/run/php/php8.5-fpm-oa.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 60s;
    }

    location ~ /\.(env|git|svn) { deny all; return 404; }
}
EOF

ln -sf /etc/nginx/sites-available/oa /etc/nginx/sites-enabled/oa
ln -sf /etc/nginx/sites-available/oa-api /etc/nginx/sites-enabled/oa-api
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl restart nginx
ok "Nginx 配置 OK"

# -------- 9. 防火墙 + 自动备份 --------
info "[9/9] 防火墙 + 自动备份..."

# UFW
ufw --force reset >/dev/null 2>&1
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 8081/tcp
ufw --force enable >/dev/null
ok "UFW 防火墙 (22/80/8081)"

# fail2ban
systemctl enable --now fail2ban >/dev/null 2>&1
ok "fail2ban 启用"

# 备份 cron
cat > /etc/cron.d/oa-backup <<'EOF'
# 每天 02:00 备份
0 2 * * * root /opt/oa/scripts/backup.sh >/var/log/oa-backup.log 2>&1
EOF
mkdir -p /opt/oa/scripts
cp -f "${SCRIPT_DIR}/scripts/backup.sh" /opt/oa/scripts/backup.sh
chmod +x /opt/oa/scripts/backup.sh
# 健康检查 cron (每 5 分钟)
cat > /etc/cron.d/oa-healthcheck <<'EOF'
*/5 * * * * root /opt/oa/scripts/healthcheck.sh >/var/log/oa-healthcheck.log 2>&1
EOF
cp -f "${SCRIPT_DIR}/scripts/healthcheck.sh" /opt/oa/scripts/healthcheck.sh
chmod +x /opt/oa/scripts/healthcheck.sh
ok "备份 + 健康检查 cron"

# -------- 验证 --------
info "========== 部署验证 =========="
SERVER_IP=$(hostname -I | awk '{print $1}')

# 测 API
API_TEST=$(curl -sS -o /dev/null -w "%{http_code}" "http://127.0.0.1:8081/api/settings" 2>/dev/null || echo "fail")
[[ "$API_TEST" == "200" ]] && ok "API http://127.0.0.1:8081 → 200" || warn "API 返回 $API_TEST (检查 php-fpm / nginx)"

# 测前端
WEB_TEST=$(curl -sS -o /dev/null -w "%{http_code}" "http://127.0.0.1/" 2>/dev/null || echo "fail")
[[ "$WEB_TEST" == "200" ]] && ok "Web http://127.0.0.1/ → 200" || warn "Web 返回 $WEB_TEST"

echo ""
echo "===================================================="
echo "  ${G}部署完成!${N}"
echo "  前端:    http://$SERVER_IP/"
echo "  API:     http://$SERVER_IP:8081/api"
echo "  默认账号: admin / <已脱敏>"
echo "           manager / 123456"
echo "           system / System@123"
echo "  DB 备份: 每天 02:00 自动 → $BACKUP_DIR"
echo "  健康检查: 每 5 分钟自动"
echo "===================================================="
echo ""
warn "⚠️  必做后续:"
echo "  1. 修改 DB / Redis 密码 (改 oa_api_root/.env + PG / Redis 配置)"
echo "  2. 修改默认账号密码"
echo "  3. 配置 HTTPS (Let's Encrypt)"
echo "  4. 浏览器访问 http://$SERVER_IP 用 admin / <已脱敏> 登录"
