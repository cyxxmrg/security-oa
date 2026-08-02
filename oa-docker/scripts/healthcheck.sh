#!/bin/bash
# 冒烟测试: 验证 4 容器 + nginx 80 + API login
set -e
cd "$(dirname "$0")/.."

. ./.env
HOST="${OA_HOST:-127.0.0.1}"
PORT="${WEB_PORT:-80}"
BASE="http://${HOST}:${PORT}"

PASS=0; FAIL=0
check() {
  local name=$1; local cmd=$2
  if eval "$cmd" >/dev/null 2>&1; then
    echo "  ✅ $name"
    PASS=$((PASS+1))
  else
    echo "  ❌ $name"
    FAIL=$((FAIL+1))
  fi
}

echo "🔍 [1/5] 容器状态..."
check "db running"    "docker compose ps db | grep -q 'Up'"
check "redis running" "docker compose ps redis | grep -q 'Up'"
check "api running"   "docker compose ps api | grep -q 'Up'"
check "web running"   "docker compose ps web | grep -q 'Up'"

echo "🔍 [2/5] 容器内部健康..."
check "pg_isready"  "docker compose exec -T db pg_isready -U $DB_USERNAME -d $DB_DATABASE"
check "redis ping"  "docker compose exec -T redis redis-cli -a $REDIS_PASSWORD --no-auth-warning ping | grep -q PONG"

echo "🔍 [3/5] nginx 80 端口..."
check "nginx /healthz"  "curl -fsS http://${HOST}:${PORT}/healthz"
check "nginx /"         "curl -fsS http://${HOST}:${PORT}/ | grep -q '<div id=\"app\"'"
check "nginx /up"       "curl -fsS http://${HOST}:${PORT}/up | head -c 1 | grep -q ."

echo "🔍 [4/5] API 登录..."
LOGIN_RESP=$(curl -fsS -X POST "http://${HOST}:${PORT}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"admin\",\"password\":\"<已脱敏>\"}" 2>/dev/null || echo "{}")
if echo "$LOGIN_RESP" | grep -q '"token"'; then
  echo "  ✅ /api/auth/login → token ok"
  PASS=$((PASS+1))
  TOKEN=$(echo "$LOGIN_RESP" | grep -oE '"token":"[^"]+"' | cut -d'"' -f4)
  echo "🔍 [5/5] 带 token 调 /api/users (列表) ..."
  # /api/user 不存在, 用 /api/users 测 token 鉴权
  USERS_RESP=$(curl -fsS "http://${HOST}:${PORT}/api/users" -H "Authorization: Bearer $TOKEN" 2>/dev/null || echo "{}")
  if echo "$USERS_RESP" | grep -q '"data"\|"code":0\|"code":200'; then
    echo "  ✅ /api/users (带 token 鉴权)"
    PASS=$((PASS+1))
  else
    echo "  ⚠️  /api/users: $(echo $USERS_RESP | head -c 100)"
  fi
else
  echo "  ❌ /api/auth/login: $LOGIN_RESP"
  FAIL=$((FAIL+1))
fi

echo ""
echo "========================================"
echo "结果: $PASS 通过 / $FAIL 失败"
echo "========================================"
[ $FAIL -eq 0 ] && exit 0 || exit 1
