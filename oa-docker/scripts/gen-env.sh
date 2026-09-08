#!/bin/bash
# 生成随机 APP_KEY / DB_PASSWORD / REDIS_PASSWORD
set -e
cd "$(dirname "$0")/.."

[ -f .env ] || { echo "❌ .env 不存在"; exit 1; }

# 强密码 (24 字符, 无特殊符号)
gen() { openssl rand -base64 32 | tr -d '/+=\n' | head -c 32; }

APP_KEY="base64:$(openssl rand -base64 32)"
DB_PASSWORD=$(gen)
REDIS_PASSWORD=$(gen)

# 替换占位符 (即使 .env 已有值也强制覆盖)
sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|; \
        s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|; \
        s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=$REDIS_PASSWORD|" .env

chmod 600 .env

echo "✅ 密钥已生成"
echo "   APP_KEY       = $APP_KEY"
echo "   DB_PASSWORD   = $DB_PASSWORD"
echo "   REDIS_PASSWORD= $REDIS_PASSWORD"
echo "   (写入 .env, 权限 600)"
