# OA 部署后必做清单 (Post-Install Checklist)

部署成功跑完后, **强烈建议** 1 小时内做完这些事:

---

## 1. 改默认账号密码 (必做, 立即)

默认账号:

| 账号 | 密码 | 角色 |
|------|------|------|
| admin | <已脱敏> | 系统管理员 |
| manager | 123456 | 业务经理 |
| user | 123456 | 普通业务 |
| system | System@123 | 系统账号 (不要登前端) |
| finance | 123456 | 财务 |
| oa_business_admin | <已脱敏> | 业务测试账号 |

**怎么改**:

- 登录后右上角 → 个人中心 → 改密
- 或直接 `sudo -u postgres psql` UPDATE `users.password`

```sql
-- 改 admin 密码 (用 bcrypt 后的 hash)
UPDATE users SET password = '$2y$10$...' WHERE username = 'admin';
```

> 用脚本生成 hash:
> ```php
> php -r "echo password_hash('你的新密码', PASSWORD_BCRYPT);"
> ```

---

## 2. 改 DB / Redis 密码 (必做, 当天)

```bash
# 改 PG
NEW_PG="你的新PG密码"
sudo -u postgres psql -c "ALTER USER oa_user WITH PASSWORD '$NEW_PG';"
sudo sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$NEW_PG/" /var/www/oa-api/.env

# 改 Redis
NEW_RED="你的新Redis密码"
sudo sed -i "s/^requirepass .*/requirepass $NEW_RED/" /etc/redis/redis.conf
sudo sed -i "s/^REDIS_PASSWORD=.*/REDIS_PASSWORD=$NEW_RED/" /var/www/oa-api/.env

# 重启
sudo systemctl restart redis-server php8.3-fpm
```

---

## 3. 配 HTTPS (强烈建议, 1 周内)

```bash
# 装 certbot
sudo apt install -y certbot python3-certbot-nginx

# 申请证书 (域名必须已解析到本机)
sudo certbot --nginx -d your-domain.com

# 自动续期已配好, 90 天续一次
sudo certbot renew --dry-run
```

---

## 4. 验证每日自动备份 (重要)

```bash
# 看 cron
crontab -l
cat /etc/cron.d/oa-backup

# 手动跑一次
sudo bash /opt/oa/scripts/backup.sh

# 验证产物
ls -la /var/backups/oa/pg/
# 应该看到:
#   pg_20260629_020001.sql.gz   (~300KB)
#   storage_20260629_020001.tar.gz
```

---

## 5. 验证健康检查

```bash
# 手动跑
sudo bash /opt/oa/scripts/healthcheck.sh
# 输出:
#   [2026-06-29 02:05:01] web OK
#   [2026-06-29 02:05:01] api OK
#   [2026-06-29 02:05:01] pg OK
#   [2026-06-29 02:05:01] redis OK

# 看定时日志
tail /var/log/oa-healthcheck.log
```

---

## 6. 防火墙收口

```bash
sudo ufw status
# 应该是:
#   22/tcp    ALLOW
#   80/tcp    ALLOW
#   8081/tcp  ALLOW  (前端反代会到这, 但浏览器只走 80)

# 如果走 nginx 反代 (默认), 8081 可以不外开
# 只允许本地访问
sudo ufw delete allow 8081/tcp
sudo sed -i 's/listen 8081/listen 127.0.0.1:8081/' /etc/nginx/sites-available/oa-api
sudo nginx -t && sudo systemctl reload nginx
```

---

## 7. 系统 hardening (推荐, 1 周内)

```bash
# 7.1 自动安全更新
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades

# 7.2 swap (2GB+ 小内存机器)
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# 7.3 时区
sudo timedatectl set-timezone Asia/Shanghai
date
```

---

## 8. 监控 (推荐, 1 周内)

部署后建议接 Prometheus + Grafana 监控。简单的方案:

- 装 [node_exporter](https://prometheus.io/download/#node_exporter) 暴露 :9100
- 装 [nginx_exporter](https://github.com/nginxinc/nginx-prometheus-exporter) 暴露 :9113
- 在 Grafana 配面板, 监控 CPU / 内存 / 磁盘 / API 响应时间

---

## 9. 日志轮转

Laravel log 默认没轮转, 跑久了会很大。配 logrotate:

```bash
sudo tee /etc/logrotate.d/oa-laravel <<'EOF'
/var/www/oa-api/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.3-fpm > /dev/null 2>&1
    endscript
}
EOF
```

---

## 10. 应用层配置

登录后建议改:

- **系统设置 → 通用**: 公司名称 / Logo / 主题色
- **系统设置 → 权限**: 检查 admin 角色是否拥有所有权限 (V1.2.8 默认全勾)
- **系统设置 → 字典**: 维护你公司的行业 / 类别 / 业务类型
- **财务设置**: 付款方式 / 税率
- **业务设置**: 项目状态 / 商机阶段 / 客户分类

---

## 验收 Checklist

- [ ] admin 改密
- [ ] DB / Redis 改密
- [ ] HTTPS 配好
- [ ] 备份跑通
- [ ] 健康检查 OK
- [ ] 防火墙收口
- [ ] 自动安全更新
- [ ] 监控接入
- [ ] 日志轮转配好
- [ ] 业务参数配好
