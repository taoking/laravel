# Docker 部署与发布回滚 Runbook

## 本地 Docker 启动

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm install
docker compose exec app npm run build
```

访问路径：

- 后台：`http://127.0.0.1:8000/admin`
- API 文档：`http://127.0.0.1:8000/docs/api`
- 健康检查：`http://127.0.0.1:8000/up`

## 服务组成

- `nginx`：HTTP 入口。
- `app`：PHP-FPM。
- `mysql`：业务数据库。
- `redis`：缓存、限流、队列。
- `queue`：Queue Worker。
- `scheduler`：Laravel Scheduler。

## 生产发布步骤

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan down --render="errors::503"
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
php artisan up
```

## 回滚步骤

1. 切回上一版本代码。
2. 恢复上一版本构建产物。
3. 如迁移不可逆，执行备份恢复或补偿脚本。
4. 执行 `php artisan config:cache`。
5. 执行 `php artisan queue:restart`。
6. 验证 `/up`、`/api/v1/health`、`/docs/api`。

## 502 / 504 排查

- Nginx 到 PHP-FPM 网络是否可达。
- PHP-FPM 是否进程耗尽。
- `fastcgi_read_timeout` 是否小于业务耗时。
- 数据库连接是否耗尽。
- Worker 是否处理了应该异步化的任务。

## 配置文件

- Docker Compose：`docker-compose.yml`
- PHP 镜像：`docker/php/Dockerfile`
- OPcache：`docker/php/opcache.ini`
- Nginx：`docker/nginx/default.conf`
- Supervisor：`docker/supervisor/worker.conf`
