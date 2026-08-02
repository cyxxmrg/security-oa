# OA 常见问题排查 (Troubleshooting)

## 🔴 部署阶段

### `composer install` 失败

```
[RuntimeException] Could not delete ...
```

**解法**:
```bash
cd /var/www/oa-api
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction \
    --no-scripts  # 跳过 scripts 段 (post-install 通常是包级)
```

### `php artisan migrate` 失败 / 卡住

```bash
# 1. 看具体错
sudo -u www-data php artisan migrate --force -vvv
# 2. 看 PG 状态
sudo -u postgres pg_isready
sudo -u postgres psql -c "\l"
```

### PG 认证失败

```
SQLSTATE[08006] [7] FATAL: Peer authentication failed for user "oa_user"
```

**解法**: 改 `pg_hba.conf`, 把 `local all all peer` → `local all all md5`
```bash
PG_HBA=$(sudo -u postgres psql -tAc "SHOW hba_file;")
sudo sed -i 's/^local\s\+all\s\+all\s\+peer/local all all md5/' "$PG_HBA"
sudo -u postgres psql -c "SELECT pg_reload_conf();"
```

### Redis 密码认证失败

```
NOAUTH Authentication required
```

```bash
# 1. 看实际配置
grep "^requirepass" /etc/redis/redis.conf
# 2. 手动设
sudo sed -i 's/^requirepass.*/requirepass 你的密码/' /etc/redis/redis.conf
sudo systemctl restart redis-server
# 3. 验证
redis-cli -a 你的密码 --no-auth-warning ping
```

### 前端 dist 部署后 502

```bash
# 1. 看 nginx 错
sudo tail -30 /var/log/nginx/error.log
# 2. 常见: index.html 缺
ls -la /var/www/oa-web/index.html
# 3. 重新部署
sudo rsync -av --delete /tmp/oa-baremetal/pc-web-dist/ /var/www/oa-web/
sudo systemctl reload nginx
```

---

## 🟡 运行阶段

### 502 Bad Gateway

```bash
# 1. FPM 死没死
sudo systemctl status php8.3-fpm
sudo systemctl restart php8.3-fpm

# 2. socket 存在不
ls -la /run/php/php8.3-fpm-oa.sock

# 3. nginx 配置有错
sudo nginx -t
```

### 登录返回 500

```bash
# 1. 看 Laravel log
sudo tail -50 /var/www/oa-api/storage/logs/laravel-$(date +%Y-%m-%d).log

# 2. 常见: .env 配置错
sudo cat /var/www/oa-api/.env | grep -E "DB_|REDIS_"

# 3. 重启 FPM 加载新 .env
sudo systemctl restart php8.3-fpm
```

### 浏览器一直显示旧版本 (SW 缓存)

```
打开页面发现是 v1.2.4, 但服务器已经部署 v1.2.8
```

**根因**: PWA Service Worker 把 JS/CSS Cache First 1 年。

**解法** (3 选 1):
1. 浏览器: F12 → Application → Service Workers → Unregister → 强制刷新 (Ctrl+Shift+R)
2. 改 SW 策略: `/var/www/oa-web/sw.js` 改 `CacheFirst` → `NetworkFirst`
3. 等用户 1 周后自动失效 (过 1 年)

### 文件上传 404

```bash
# storage 软链不在
ls -la /var/www/oa-api/storage/
# 应该是 -> ../storage/app/public

# 重建
cd /var/www/oa-api
sudo -u www-data php artisan storage:link
```

### 慢 / 卡

```bash
# 1. 慢日志
sudo tail -f /var/log/nginx/oa-api.access.log | awk '$NF > 5'

# 2. FPM 进程
ps -ef | grep php-fpm | wc -l
# 改 /etc/php/8.3/fpm/pool.d/oa.conf
#   pm.max_children = 50  (从 30)

# 3. Redis
redis-cli -a 你的密码 --no-auth-warning INFO memory
```

---

## 🟢 性能 / 扩展

### 加 worker (queue)

```bash
# queue worker
sudo tee /etc/systemd/system/oa-queue.service <<'EOF'
[Unit]
Description=OA Queue Worker
After=redis-server.service
[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/oa-api
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=60
Restart=always
RestartSec=3
[Install]
WantedBy=multi-user.target
EOF

sudo systemctl enable --now oa-queue
```

### 加 cron (定时任务)

```bash
sudo tee /etc/systemd/system/oa-schedule.service <<'EOF'
[Unit]
Description=OA Scheduler
[Service]
User=www-data
WorkingDirectory=/var/www/oa-api
ExecStart=/usr/bin/php artisan schedule:work
Restart=always
[Install]
WantedBy=multi-user.target
EOF

sudo systemctl enable --now oa-schedule
```

### 加 Redis 集群

见 `oa-baremetal/docs/HORIZONTAL_SCALE.md` (未写, 后续补)

---

## 📞 出问题找谁

1. **看日志**: `sudo tail -f /var/log/oa-healthcheck.log`
2. **跑健康检查**: `sudo bash /opt/oa/scripts/healthcheck.sh`
3. **看 Laravel 错**: `sudo tail /var/www/oa-api/storage/logs/laravel-*.log`
4. **看 nginx 错**: `sudo tail /var/log/nginx/error.log`
