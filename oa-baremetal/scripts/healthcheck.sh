#!/usr/bin/env bash
# =============================================================
#  OA 健康检查 (每 5 分钟)
#  监控: API 200, Web 200, PG OK, Redis OK, 磁盘 > 80% 告警
# =============================================================
set -uo pipefail

ALERT="/var/log/oa-healthcheck.log"
WEB_URL="http://127.0.0.1/"
API_URL="http://127.0.0.1:8081/api/settings"

err=0
ts() { date "+%Y-%m-%d %H:%M:%S"; }

# 1. Web
code=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 5 "$WEB_URL" 2>/dev/null || echo "fail")
if [[ "$code" == "200" ]]; then
    echo "[$(ts)] web OK"
else
    echo "[$(ts)] web FAIL ($code) — 尝试 nginx reload"
    systemctl reload nginx || true
    err=$((err+1))
fi

# 2. API
code=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 5 "$API_URL" 2>/dev/null || echo "fail")
if [[ "$code" == "200" ]] || [[ "$code" == "401" ]] || [[ "$code" == "403" ]]; then
    echo "[$(ts)] api OK ($code)"
else
    echo "[$(ts)] api FAIL ($code) — 尝试 php-fpm restart"
    systemctl restart php8.3-fpm || true
    err=$((err+1))
fi

# 3. PG
if ! sudo -u postgres psql -d security_oa -c "SELECT 1" >/dev/null 2>&1; then
    echo "[$(ts)] pg FAIL"
    err=$((err+1))
else
    echo "[$(ts)] pg OK"
fi

# 4. Redis
if ! redis-cli -a oa_redis_pwd_change_me --no-auth-warning ping 2>/dev/null | grep -q PONG; then
    echo "[$(ts)] redis FAIL"
    systemctl restart redis-server || true
    err=$((err+1))
else
    echo "[$(ts)] redis OK"
fi

# 5. 磁盘
DISK_PCT=$(df -P /var/www | tail -1 | awk '{print $5}' | tr -d '%')
if [[ $DISK_PCT -ge 80 ]]; then
    echo "[$(ts)] DISK WARN ${DISK_PCT}%"
    err=$((err+1))
fi

if [[ $err -gt 0 ]]; then
    echo "[$(ts)] HEALTH: $err issues" >&2
    exit 1
fi
exit 0
