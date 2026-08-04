#!/usr/bin/env bash
#
# OA 安防运维系统 —— Ubuntu 一键安装脚本
# 适用: Ubuntu 22.04 / 24.04 LTS (其他版本未测试)
# 功能: 安装 PHP8.4+Composer+Node20+Nginx+PostgreSQL+Redis，
#       拉取源码、配置后端、构建前端、写入 Nginx 站点并启动。
#
# 用法:
#   sudo bash install.sh                      # 默认装到 /var/www/oa，域名取本机 IP
#   sudo bash install.sh --domain oa.example.com
#   sudo bash install.sh --dir /srv/oa --domain oa.example.com
#   sudo bash install.sh --skip-clone         # 代码已在 --dir 中，跳过 git clone
#   sudo bash install.sh --demo               # 额外创建一个演示管理员 admin (随机密码)
#   sudo bash install.sh --force              # 目标目录已存在也强制覆盖重装
#
# 安全说明: 本脚本不硬编码或向标准输出打印任何真实密码，默认启用 HTTPS。
#
set -euo pipefail

# ---------- 颜色与日志 ----------
if [ -t 1 ]; then
  C_R="\033[31m"; C_G="\033[32m"; C_Y="\033[33m"; C_B="\033[34m"; C_N="\033[0m"
else
  C_R=""; C_G=""; C_Y=""; C_B=""; C_N=""
fi
log()  { echo -e "${C_G}[OK]${C_N}  $*"; }
info() { echo -e "${C_B}[..]${C_N}  $*"; }
warn() { echo -e "${C_Y}[!!]${C_N}  $*"; }
err()  { echo -e "${C_R}[ERROR]${C_N}  $*" >&2; }

# ---------- 参数解析 ----------
DOMAIN=""
INSTALL_DIR="/var/www/oa"
SKIP_CLONE=0
DEMO=0
FORCE=0
TLS_CERT=""
TLS_KEY=""
REPO_URL="https://github.com/guo383975-design/security-oa.git"

while [ $# -gt 0 ]; do
  case "$1" in
    --domain)      DOMAIN="$2"; shift 2;;
    --dir)         INSTALL_DIR="$2"; shift 2;;
    --skip-clone)  SKIP_CLONE=1; shift;;
    --demo)        DEMO=1; shift;;
    --force)       FORCE=1; shift;;
    --tls-cert)    TLS_CERT="$2"; shift 2;;
    --tls-key)     TLS_KEY="$2"; shift 2;;
    -h|--help)     sed -n '3,30p' "$0"; exit 0;;
    *) err "未知参数: $1"; exit 1;;
  esac
done

# ---------- 前置检查 ----------
if [ "$(id -u)" -ne 0 ]; then
  err "请使用 root 运行: sudo bash install.sh"
  exit 1
fi

if ! command -v lsb_release >/dev/null 2>&1; then
  err "未找到 lsb_release，可能不是 Ubuntu 系统。"
  exit 1
fi

CODENAME="$(lsb_release -cs)"
case "$CODENAME" in
  jammy|noble) : ;;   # 22.04 / 24.04
  *) warn "检测到 Ubuntu '$CODENAME'，本脚本主要适配 jammy/noble，继续执行但可能需手动调整。" ;;
esac

if [ -z "$DOMAIN" ]; then
  # 取第一个非内环 IPv4 作为默认访问域名
  DOMAIN="$(hostname -I 2>/dev/null | awk '{print $1}')"
  [ -z "$DOMAIN" ] && DOMAIN="localhost"
  info "未指定 --domain，使用本机地址: $DOMAIN"
fi

# 目标目录检查
if [ -d "$INSTALL_DIR/.git" ] || [ -d "$INSTALL_DIR/pc-api" ]; then
  if [ "$FORCE" -eq 0 ] && [ "$SKIP_CLONE" -eq 0 ]; then
    err "目标目录 $INSTALL_DIR 已存在代码。请换 --dir，或加 --force 覆盖，或用 --skip-clone 复用现有代码。"
    exit 1
  fi
fi

# 随机密码生成
# 注意: 管道末尾 head -c 会让 tr 收到 SIGPIPE(141)，在 set -euo pipefail 下会静默终止脚本，
# 因此函数末尾必须加 || true 吞掉该退出码，调用方才能拿到非空的随机串。
rand_pass() { LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom 2>/dev/null | head -c 16 || true; }
DB_PASS="$(rand_pass)"
[ -z "$DB_PASS" ] && DB_PASS="$(head -c 256 /dev/urandom | sha256sum | cut -c1-16)"

# ---------- 1. 系统基础工具 ----------
info "更新软件源并安装基础工具..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y -q curl ca-certificates gnupg lsb-release git unzip openssl supervisor software-properties-common

# ---------- 2. PHP 8.4 (Ondrej Ubuntu PPA) ----------
info "添加 Ondrej PHP PPA 并安装 PHP 8.4 + 扩展..."
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y -q \
  php8.4 php8.4-cli php8.4-fpm php8.4-common \
  php8.4-pgsql php8.4-redis php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl
PHP_VER="8.4"
update-alternatives --set php "/usr/bin/php${PHP_VER}"

# ---------- 3. Composer ----------
if ! command -v composer >/dev/null 2>&1; then
  info "安装 Composer..."
  EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  ACTUAL="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
  if [ "$EXPECTED" != "$ACTUAL" ]; then
    err "Composer 安装器签名校验失败，已中止以防供应链风险。"
    rm -f composer-setup.php
    exit 1
  fi
  php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
  rm -f composer-setup.php
else
  info "Composer 已存在，跳过安装。"
fi

# ---------- 4. Node.js 20 (NodeSource) ----------
if ! command -v node >/dev/null 2>&1 || [ "$(node -v | cut -d. -f1 | tr -d v)" -lt 18 ]; then
  info "安装 Node.js 20 (LTS)..."
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y -q nodejs
else
  info "Node.js 已满足要求 ($(node -v))，跳过安装。"
fi

# ---------- 5. Nginx / PostgreSQL / Redis ----------
info "安装 Nginx、PostgreSQL、Redis..."
apt-get install -y -q nginx postgresql postgresql-contrib redis-server

# ---------- 6. 启动基础服务 ----------
info "启动 PostgreSQL / Redis / Nginx..."
systemctl enable --now postgresql redis-server nginx

# ---------- 7. 创建数据库与用户 ----------
info "创建数据库 security_oa 与用户 oa_user..."
set +e
# 每次都同步 oa_user 密码: 首次创建, 后续重跑 ALTER 同步, 避免 .env 与库中实际密码因多次随机生成而不一致
if sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='oa_user'" | grep -q 1; then
  sudo -u postgres psql -c "ALTER USER oa_user WITH PASSWORD '$DB_PASS' CREATEDB;" >/dev/null 2>&1
else
  sudo -u postgres psql -c "CREATE USER oa_user WITH PASSWORD '$DB_PASS' CREATEDB;" >/dev/null 2>&1
fi
sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='security_oa'" | grep -q 1 || \
  sudo -u postgres psql -c "CREATE DATABASE security_oa OWNER oa_user;"
set -e

SYSTEM_PASS=""
if [ "$(sudo -u postgres psql -d security_oa -tAc "SELECT to_regclass('public.users') IS NOT NULL")" = "t" ]; then
  SYSTEM_EXISTS="$(sudo -u postgres psql -d security_oa -tAc "SELECT 1 FROM users WHERE username='system' LIMIT 1")"
else
  SYSTEM_EXISTS=""
fi
if [ "$SYSTEM_EXISTS" != "1" ]; then
  SYSTEM_PASS="$(rand_pass)A1"
  [ "${#SYSTEM_PASS}" -ge 14 ] || SYSTEM_PASS="$(openssl rand -hex 12)A1"
fi

# ---------- 8. 获取源码 ----------
if [ "$SKIP_CLONE" -eq 0 ]; then
  info "克隆源码到 $INSTALL_DIR ..."
  if [ "$FORCE" -eq 1 ] && [ -d "$INSTALL_DIR" ]; then
    rm -rf "$INSTALL_DIR"
  fi
  git clone --depth 1 "$REPO_URL" "$INSTALL_DIR"
else
  info "跳过 clone，使用已有目录 $INSTALL_DIR"
fi

API_DIR="$INSTALL_DIR/pc-api"
WEB_DIR="$INSTALL_DIR/pc-web"

# ---------- 9. 配置 pc-api ----------
info "配置后端 pc-api ..."
cd "$API_DIR"
# 全新 clone 后 bootstrap/cache、storage 等运行时目录被 .gitignore 排除, 可能不存在,
# 必须在 composer install (其 post-update-cmd 会触发 artisan) 之前创建, 否则 artisan 启动失败。
mkdir -p bootstrap/cache storage/app/public storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
cp -n .env.example .env
sed -i "s#^APP_ENV=.*#APP_ENV=production#" .env
sed -i "s#^APP_DEBUG=.*#APP_DEBUG=false#" .env
sed -i "s#^APP_URL=.*#APP_URL=https://${DOMAIN}#" .env
sed -i "s#^DB_PASSWORD=.*#DB_PASSWORD=${DB_PASS}#" .env
sed -i "s#^SANCTUM_STATEFUL_DOMAINS=.*#SANCTUM_STATEFUL_DOMAINS=${DOMAIN}#" .env
# Laravel 11 读取 CACHE_STORE (旧键 CACHE_DRIVER 已废弃); 缺失会使缓存回退到
# database 驱动, 因缺 cache 表导致登录等接口 500。此处幂等确保为 redis。
grep -q "^CACHE_STORE=" .env || echo "CACHE_STORE=redis" >> .env
sed -i "s#^CACHE_STORE=.*#CACHE_STORE=redis#" .env
if [ -n "$SYSTEM_PASS" ]; then
  sed -i '/^SYSTEM_INIT_PASSWORD=/d' .env
  echo "SYSTEM_INIT_PASSWORD=${SYSTEM_PASS}" >> .env
fi

if [ ! -f composer.lock ]; then
  err "pc-api/composer.lock 缺失，拒绝执行不可复现的生产部署。"
  exit 1
fi
info "按锁文件安装 PHP 依赖 (composer)..."
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction
composer audit --locked --no-dev

info "生成应用密钥并迁移数据库..."
if grep -Eq '^APP_KEY=$' .env; then
  php artisan key:generate
fi
php artisan migrate --force
sed -i '/^SYSTEM_INIT_PASSWORD=/d' .env
php artisan storage:link --force || true

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ---------- 10. 构建 pc-web ----------
info "构建前端 pc-web (npm ci + build)..."
cd "$WEB_DIR"
rm -rf dist
npm ci
npm audit --omit=dev
npm run build

# ---------- 11. Nginx 站点 ----------
info "写入 Nginx 站点配置 ($DOMAIN)..."
TLS_SELF_SIGNED=0
if [ -z "$TLS_CERT" ] || [ -z "$TLS_KEY" ]; then
  TLS_SELF_SIGNED=1
  TLS_DIR="/etc/ssl/security-oa"
  install -d -m 0700 "$TLS_DIR"
  TLS_CERT="$TLS_DIR/server.crt"
  TLS_KEY="$TLS_DIR/server.key"
  if [ ! -f "$TLS_CERT" ] || [ ! -f "$TLS_KEY" ]; then
    warn "未提供 TLS 证书，正在生成内部自签名证书；正式域名请传 --tls-cert 与 --tls-key。"
    if [[ "$DOMAIN" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
      TLS_SAN="IP:${DOMAIN}"
    else
      TLS_SAN="DNS:${DOMAIN}"
    fi
    openssl req -x509 -nodes -newkey rsa:3072 -days 365 \
      -keyout "$TLS_KEY" -out "$TLS_CERT" -subj "/CN=${DOMAIN}" \
      -addext "subjectAltName=${TLS_SAN}" >/dev/null 2>&1
    chmod 0600 "$TLS_KEY"
  fi
fi
if [ ! -r "$TLS_CERT" ] || [ ! -r "$TLS_KEY" ]; then
  err "TLS 证书或私钥不可读。"
  exit 1
fi
if [ "$TLS_SELF_SIGNED" -eq 1 ]; then
  HSTS_DIRECTIVE=""
else
  HSTS_DIRECTIVE='add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;'
fi
cat > /etc/nginx/sites-available/oa <<NGINX_EOF
server {
    listen 80;
    server_name ${DOMAIN};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${DOMAIN};

    ssl_certificate ${TLS_CERT};
    ssl_certificate_key ${TLS_KEY};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_session_timeout 1d;
    ssl_session_cache shared:OA_SSL:10m;

    client_max_body_size 50m;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=(self)" always;
    ${HSTS_DIRECTIVE}
    add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; frame-src 'self' blob:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'" always;

    location ~ ^/storage/(tenders|purchase|external-quotes|repairs)/ { deny all; }

    # 前端静态资源 (Vue3 build 产物)
    location / {
        root ${WEB_DIR}/dist;
        index index.html;
        try_files \$uri \$uri/ /index.html;
    }

    # 后端 API (Laravel)
    location /api {
        root ${API_DIR}/public;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        # 必须显式声明 root: try_files 将 /api/* 重写到 /index.php 后,
        # 内部跳转至此 location, 若无 root 会落到 nginx 默认 root 导致 404
        root ${API_DIR}/public;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX_EOF

ln -sf /etc/nginx/sites-available/oa /etc/nginx/sites-enabled/oa
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

# ---------- 12. 重启 php-fpm ----------
systemctl restart "php${PHP_VER}-fpm"
systemctl enable "php${PHP_VER}-fpm"

# ---------- 13. Scheduler / Horizon ----------
cat > /etc/cron.d/security-oa-scheduler <<CRON_EOF
* * * * * www-data cd "${API_DIR}" && /usr/bin/php${PHP_VER} artisan schedule:run >> /dev/null 2>&1
CRON_EOF
chmod 0644 /etc/cron.d/security-oa-scheduler

cat > /etc/supervisor/conf.d/security-oa-horizon.conf <<SUPERVISOR_EOF
[program:security-oa-horizon]
process_name=%(program_name)s
command=/usr/bin/php${PHP_VER} ${API_DIR}/artisan horizon
directory=${API_DIR}
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=${API_DIR}/storage/logs/horizon.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=5
stopwaitsecs=3600
SUPERVISOR_EOF
supervisorctl reread
supervisorctl update

# ---------- 14. (可选) 演示管理员 ----------
if [ "$DEMO" -eq 1 ]; then
  info "创建演示管理员账号 admin (随机密码)..."
  DEMO_PASS="$(rand_pass)A1"
  [ "${#DEMO_PASS}" -ge 14 ] || DEMO_PASS="$(openssl rand -hex 12)A1"
  sudo -u www-data php artisan oa:seed-admin --password="$DEMO_PASS" >/dev/null
fi

# ---------- 15. 防火墙 ----------
if command -v ufw >/dev/null 2>&1; then
  ufw allow 80/tcp >/dev/null 2>&1 || true
  ufw allow 443/tcp >/dev/null 2>&1 || true
fi

CREDENTIAL_FILE="/root/security-oa-install-credentials"
{
  echo "database=security_oa"
  echo "database_user=oa_user"
  echo "database_password=${DB_PASS}"
  if [ -n "$SYSTEM_PASS" ]; then
    echo "system_username=system"
    echo "system_password=${SYSTEM_PASS}"
  fi
  if [ "$DEMO" -eq 1 ]; then echo "demo_admin_password=${DEMO_PASS}"; fi
} > "$CREDENTIAL_FILE"
chmod 0600 "$CREDENTIAL_FILE"

# ---------- 完成 ----------
echo ""
echo -e "${C_G}==================================================${C_N}"
echo -e "${C_G} OA 安防运维系统 部署完成${C_N}"
echo -e "${C_G}==================================================${C_N}"
echo -e " 访问地址 : https://${DOMAIN}"
echo -e " 代码目录 : ${INSTALL_DIR}"
echo -e " 数据库   : security_oa / 用户 oa_user"
echo -e " 凭据文件 : ${CREDENTIAL_FILE} (仅 root 可读)"
echo -e " ${C_Y}生产建议: 使用受信任 TLS 证书并配置防火墙白名单${C_N}"
echo -e "${C_G}==================================================${C_N}"
