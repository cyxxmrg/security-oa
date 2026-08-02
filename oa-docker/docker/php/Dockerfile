FROM php:8.2-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive

# 系统依赖 + PHP 扩展
RUN apt-get update && apt-get install -y --no-install-recommends \
      libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype-dev \
      libicu-dev libonig-dev cron supervisor procps \
      postgresql-client \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pgsql opcache zip intl bcmath pcntl \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP 配置优化
RUN { \
      echo 'memory_limit = 512M'; \
      echo 'upload_max_filesize = 200M'; \
      echo 'post_max_size = 220M'; \
      echo 'max_execution_time = 300'; \
      echo 'date.timezone = Asia/Shanghai'; \
      echo 'opcache.enable = 1'; \
      echo 'opcache.memory_consumption = 192'; \
      echo 'opcache.max_accelerated_files = 20000'; \
    } > /usr/local/etc/php/conf.d/zz-oa.ini

# 让 fpm 监听 0.0.0.0 (其他容器可访问)
# default bookworm 模板里 listen 可能是 ;listen = 127.0.0.1:9000, 强制改成 active
RUN sed -i 's|^;listen = 127.0.0.1:9000|listen = 0.0.0.0:9000|; \
             s|^listen = 127.0.0.1:9000|listen = 0.0.0.0:9000|' /usr/local/etc/php-fpm.d/www.conf

# 容器内代码由 bind mount 注入 (./pc-api:/var/www/oa-api)
# 注意: 实际启动时 entrypoint 会 chown -R www-data:www-data 修复 bind mount 的 1000:1000 owner
RUN mkdir -p /var/www/oa-api && chown -R www-data:www-data /var/www/oa-api

WORKDIR /var/www/oa-api

# entrypoint
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# 健康检查 - 只看 fpm 主进程是否存活 + 9000 端口监听 (避免 opcache 触发 artisan segfault)
HEALTHCHECK --interval=30s --timeout=5s --start-period=180s --retries=5 \
  CMD sh -c 'kill -0 1 2>/dev/null && cat /proc/net/tcp | grep -i 2328 >/dev/null' || exit 1

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "--nodaemonize"]
