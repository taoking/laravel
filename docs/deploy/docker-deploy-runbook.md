# Docker 部署与发布回滚 Runbook

## 本地 Docker 启动

一键验收脚本：

```bash
scripts/deploy/docker-smoke.sh
```

脚本会执行：

1. `docker compose config`
2. `docker compose up -d --build mysql redis kafka app nginx queue scheduler`
3. 配置容器内 Git `safe.directory /var/www/html`
4. `composer install`
5. `npm ci && npm run build`
6. `php artisan key:generate --force`
7. `php artisan migrate --seed --force`
8. `php artisan queue:restart`
9. 重建 Nginx，刷新 FastCGI 上游连接
10. 宿主机执行 `KAFKA_DRIVER=docker php artisan kafka:topics --create`，不可用时退回 Kafka 容器 CLI 创建 topic
11. HTTP 重试验收 `/up`、`/login`、`/docs/api`、`/api/v1/health`
12. 等待 `app`、`nginx`、`mysql`、`redis` Docker health 状态变为 healthy 后输出 `docker compose ps`

只想检查脚本和容器命令，不发起 HTTP 请求：

```bash
SKIP_HTTP=1 scripts/deploy/docker-smoke.sh
```

如果容器已经启动，只想重复执行应用初始化：

```bash
SKIP_UP=1 scripts/deploy/docker-smoke.sh
```

访问路径：

- 后台：`http://127.0.0.1:8000/admin`
- API 文档：`http://127.0.0.1:8000/docs/api`
- API 健康检查：`http://127.0.0.1:8000/api/v1/health`
- Laravel 健康检查：`http://127.0.0.1:8000/up`

最近一次真实 smoke 结果：

```text
scripts/deploy/docker-smoke.sh
Kafka topics ready: 6
app         Up (healthy)
nginx       Up (healthy)
mysql       Up (healthy)
redis       Up (healthy)
queue       Up
scheduler   Up
kafka       Up
```

已验证 HTTP 入口：`/login`、`/docs/api`、`/api/v1/health`。

## 服务组成

- `nginx`：HTTP 入口。
- `app`：PHP-FPM。
- `mysql`：业务数据库。
- `redis`：缓存、限流、队列。
- `queue`：Queue Worker。
- `scheduler`：Laravel Scheduler。
- `kafka`：单节点 Kafka，供 P1-04 消息事件流专题本地演示使用。

运行约束：

- `app`、`nginx`、`mysql`、`redis`、`queue`、`scheduler`、`kafka` 均配置 `restart: unless-stopped`。
- `queue:restart` 会让 Laravel Worker 正常退出，Compose 重启策略会重新拉起 `queue` 容器。
- PHP 镜像已安装 Node/npm 和 `phpredis` 扩展，支持容器内 `npm run build` 与 Redis Cache/Queue。
- `app` 服务使用 `node-modules` 命名卷挂载 `/var/www/html/node_modules`，避免容器内 `npm ci` 覆盖宿主机 macOS/Windows 的原生依赖。
- Nginx 使用 Docker DNS `127.0.0.11` 动态解析 `app:9000`，避免 app 容器重建后 FastCGI 指向旧 IP。

## 健康检查和状态查看

```bash
docker compose ps
docker compose logs -f nginx
docker compose logs -f app
docker compose logs -f queue
docker compose logs -f scheduler
docker compose logs -f mysql
docker compose logs -f redis
docker compose logs -f kafka
```

容器内 Laravel 检查：

```bash
docker compose exec app php artisan about
docker compose exec app php artisan route:list --except-vendor
docker compose exec app php artisan queue:failed
docker compose exec app php artisan schedule:list
```

## Kafka 本地演示

默认 `.env.example` 使用 `KAFKA_DRIVER=local`，适合自动化测试和离线演示。需要连接真实 Docker Kafka 时，使用环境变量临时切换：

```bash
docker compose up -d kafka
KAFKA_DRIVER=docker php artisan kafka:topics --create
KAFKA_DRIVER=docker php artisan kafka:produce metric.import.completed
KAFKA_DRIVER=docker php artisan kafka:consume audit-log-consumer --max=1
KAFKA_DRIVER=docker php artisan kafka:lag audit-log-consumer
```

本地测试命令：

```bash
php artisan test --filter=PhaseSevenKafkaMessagingTest
```

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

| 现象 | 排查命令 | 常见原因 | 处理方式 |
| --- | --- | --- | --- |
| 502 Bad Gateway | `docker compose logs app nginx` | PHP-FPM 未启动、容器网络不可达、FPM 配置错误 | 重启 app，检查 `php-fpm -t` 和 Nginx `fastcgi_pass app:9000` |
| 504 Gateway Timeout | `docker compose logs nginx app` | PHP 执行过慢、SQL 慢、外部请求卡住、`fastcgi_read_timeout` 不够 | 查慢 SQL、把重任务放入队列、调整 timeout |
| 数据库连接失败 | `docker compose logs mysql app` | MySQL 未 ready、账号密码错误、`.env` 与 compose 环境不一致 | 等待 healthcheck，通过 `php artisan migrate` 验证 |
| Redis 队列不消费 | `docker compose logs queue redis` | `QUEUE_CONNECTION` 错误、Redis 不可达、Worker 旧代码 | 检查 env，执行 `php artisan queue:restart` |
| Scheduler 重复执行 | `docker compose logs scheduler` | 多个 scheduler 实例同时运行 | 使用 `onOneServer()` 或只保留一个 scheduler |
| Kafka 不可达 | `docker compose logs kafka app` | advertised listener、driver 或 broker 配置错误 | 使用 `KAFKA_DRIVER=docker` 并验证 `kafka:topics --create` |
| 文件权限错误 | `docker compose logs app nginx` | `storage` 或 `bootstrap/cache` 不可写 | 调整宿主机权限或容器用户 |

## 发布和回滚补充

发布后必须重启队列 Worker：

```bash
docker compose exec app php artisan queue:restart
```

配置、路由和视图缓存：

```bash
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

回滚后建议执行：

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear
docker compose exec app php artisan queue:restart
```

## 配置文件

- Docker Compose：`docker-compose.yml`
- PHP 镜像：`docker/php/Dockerfile`
- OPcache：`docker/php/opcache.ini`
- Nginx：`docker/nginx/default.conf`
- Supervisor：`docker/supervisor/worker.conf`
