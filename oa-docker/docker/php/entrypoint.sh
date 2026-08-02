#!/bin/sh
# Laravel php-fpm 容器启动脚本
# 关键原则: 任何步骤失败都不能让容器退出 (否则 docker 会无限重启)
# 用 || true 兜底, 出错只 echo, 不中断
# ============================================
cd /var/www/oa-api

# ============== 1. .env ==============
if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env || echo "warn: cp .env.example failed"
  else
    echo "WARN: .env.example not found, using existing .env"
  fi
fi

# 用环境变量覆盖 .env 关键项 (| 表示 sed 找不到目标行时不算错)
apply_env() {
  key=$1; val=$2
  if [ -n "$val" ]; then
    sed -i "s|^${key}=.*|${key}=${val}|" .env 2>/dev/null || true
  fi
}
apply_env APP_NAME "OA"
apply_env APP_ENV "${APP_ENV:-production}"
apply_env APP_DEBUG "${APP_DEBUG:-false}"
apply_env APP_URL "${APP_URL:-http://localhost}"
apply_env APP_KEY "${APP_KEY:-}"
apply_env DB_CONNECTION "${DB_CONNECTION:-pgsql}"
apply_env DB_HOST "${DB_HOST:-db}"
apply_env DB_PORT "${DB_PORT:-5432}"
apply_env DB_DATABASE "${DB_DATABASE:-security_oa}"
apply_env DB_USERNAME "${DB_USERNAME:-oa_user}"
apply_env DB_PASSWORD "${DB_PASSWORD:-}"
apply_env REDIS_HOST "${REDIS_HOST:-redis}"
apply_env REDIS_PORT "${REDIS_PORT:-6379}"
apply_env REDIS_PASSWORD "${REDIS_PASSWORD:-}"
apply_env CACHE_STORE "${CACHE_STORE:-redis}"
apply_env SESSION_DRIVER "${SESSION_DRIVER:-redis}"
apply_env QUEUE_CONNECTION "${QUEUE_CONNECTION:-redis}"

# APP_KEY 没设 → 生成一个 (避免 Sanctum 报 "No application encryption key")
if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
  GEN_KEY="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
  apply_env APP_KEY "$GEN_KEY"
  echo "[entrypoint] APP_KEY 已生成"
fi

# ============== 2. composer install (首次启动或 composer.json 更新) ==============
if [ ! -d vendor ] || [ composer.json -nt vendor/autoload.php ] 2>/dev/null; then
  echo "[entrypoint] composer install ..."
  export COMPOSER_NO_AUDIT=1
  composer config --no-plugins policy.advisories.block false 2>/dev/null || true
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-security-blocking 2>&1 | tail -10 || echo "WARN: composer install 失败, 继续"

  # 发布 sanctum migration (Laravel 11 默认不带, 登录要 personal_access_tokens 表)
  echo "[entrypoint] publish sanctum migration ..."
  php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider" --tag=sanctum-migrations --force 2>&1 | tail -3 || echo "WARN: sanctum publish 失败"
fi

# ============== 3. 目录 + owner (bind mount 1000:1000 → www-data) ==============
mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app 2>/dev/null || true
chown -R www-data:www-data /var/www/oa-api 2>/dev/null || echo "WARN: chown 失败 (不影响启动)"
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# ============== 4. 等 db ==============
echo "[entrypoint] 等待 db ..."
for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20 21 22 23 24 25 26 27 28 29 30; do
  if php -r "new PDO('pgsql:host=${DB_HOST:-db};port=${DB_PORT:-5432};dbname=${DB_DATABASE:-security_oa}','${DB_USERNAME:-oa_user}','${DB_PASSWORD}');" 2>/dev/null; then
    echo "[entrypoint] db ok"
    break
  fi
  sleep 2
done

# ============== 5. 修复 117 老 spatie 表 (兼容老数据) ==============
echo "[entrypoint] 兼容老 spatie 表 ..."
php -r "
\$pdo = @new PDO('pgsql:host=${DB_HOST:-db};port=${DB_PORT:-5432};dbname=${DB_DATABASE:-security_oa}','${DB_USERNAME:-oa_user}','${DB_PASSWORD}');
if (!\$pdo) { echo 'WARN: db 不可达, 跳过兼容\n'; exit(0); }
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  \$tables = \$pdo->query(\"SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name IN ('permission_role','role_has_permissions','model_has_permissions','model_has_roles','role_has_users')\")->fetchAll(PDO::FETCH_COLUMN);

  if (in_array('permission_role', \$tables) && !in_array('role_has_permissions', \$tables)) {
    \$pdo->exec('CREATE OR REPLACE VIEW role_has_permissions AS SELECT role_id, permission_id FROM permission_role');
    echo '  view role_has_permissions -> permission_role' . PHP_EOL;
  }
  if (!in_array('model_has_permissions', \$tables)) {
    \$pdo->exec('CREATE TABLE IF NOT EXISTS model_has_permissions (permission_id BIGINT, model_type VARCHAR(125), model_id BIGINT)');
  }
  if (!in_array('model_has_roles', \$tables)) {
    \$pdo->exec('CREATE TABLE IF NOT EXISTS model_has_roles (role_id BIGINT, model_type VARCHAR(125), model_id BIGINT)');
  }
  if (!in_array('role_has_users', \$tables)) {
    \$pdo->exec('CREATE TABLE IF NOT EXISTS role_has_users (role_id BIGINT, user_id BIGINT)');
  }
  echo '  spatie 兼容完成' . PHP_EOL;
} catch (Exception \$e) {
  echo 'WARN: spatie 兼容: ' . \$e->getMessage() . PHP_EOL;
}
" 2>&1 || echo "WARN: spatie 兼容步骤失败, 继续"

# ============== 6. 补齐老 schema 缺的列 (Laravel 11 + 老 migrate 兼容) ==============
echo "[entrypoint] 补齐老 schema 列 ..."
php -r "
\$pdo = @new PDO('pgsql:host=${DB_HOST:-db};port=${DB_PORT:-5432};dbname=${DB_DATABASE:-security_oa}','${DB_USERNAME:-oa_user}','${DB_PASSWORD}');
if (!\$pdo) { exit(0); }
try {
  \$pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL');
  \$pdo->exec(\"ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS group_name VARCHAR(64) DEFAULT 'general'\");
  \$pdo->exec('ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS description TEXT');
  \$pdo->exec('ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NULL');
  \$pdo->exec('ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL');
  echo '  users.deleted_at + system_settings 4 列 OK' . PHP_EOL;
} catch (Exception \$e) {
  echo 'WARN: schema 补齐: ' . \$e->getMessage() . PHP_EOL;
}
" 2>&1 || true

# ============== 7. migrate (幂等) ==============
echo "[entrypoint] migrate ..."
php artisan migrate --force 2>&1 | tail -10 || echo "WARN: migrate 失败, 继续"

# ============== 8. seed ==============
if [ "${OA_FRESH_INSTALL:-0}" = "1" ]; then
  echo "[entrypoint] seed (首次安装) ..."
  php artisan db:seed --class=DatabaseSeeder --force 2>&1 | tail -3 || echo "WARN: DatabaseSeeder 失败"
  php artisan db:seed --class=PermissionRoleSeeder --force 2>&1 | tail -3 || echo "WARN: PermissionRoleSeeder 失败"
  php artisan oa:disk-init 2>&1 | tail -3 || echo "WARN: disk-init 失败"
fi
if [ "${OA_AUTO_SEED:-0}" = "1" ]; then
  php artisan db:seed --class=PermissionRoleSeeder --force 2>&1 | tail -3 || true
fi

# ============== 9. cache + storage:link ==============
echo "[entrypoint] cache ..."
php artisan view:clear 2>&1 | tail -2 || true
php artisan storage:link 2>&1 | tail -2 || true

# ============== 10. 启动 fpm (前台) ==============
echo "[entrypoint] ✅ oa-api 启动 fpm"

# 关键: 任何前面的步骤失败都不能影响 fpm 启动
# (即使 migrate 失败, 至少 web 服务能起来, 看 healthz 排查)
exec php-fpm --nodaemonize