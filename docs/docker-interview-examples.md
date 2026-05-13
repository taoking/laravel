# Docker Interview Examples

本文档对应长期计划中的 Docker、本地开发环境和部署进阶主线，用于复盘 Dockerfile、Compose、镜像、容器、volume、network、healthcheck、日志、密钥和发布风险。

## 学习目标

- 能说清镜像、容器、volume、network、端口映射和服务发现。
- 能解释 Laravel 容器内为什么使用 `mysql`、`redis`、`rabbitmq` 作为 host。
- 能区分本地开发 Compose 和生产镜像/部署策略。
- 能回答容器日志、健康检查、权限、密钥和数据库迁移上线顺序。

## 源码入口

- Dockerfile：`docker/php/8.3/Dockerfile`
- Compose：`docker-compose.yml`
- 示例逻辑：`app/Learning/LaravelInterview/Support/DockerInterviewExamples.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewDockerCommand.php`
- 测试：`tests/Feature/InterviewDockerCommandTest.php`

## 运行方式

```bash
docker compose exec laravel.test php artisan interview:docker
```

输出完整 JSON：

```bash
docker compose exec laravel.test php artisan interview:docker --json
```

## 当前 Compose 服务

- `laravel.test`：PHP 8.3 Laravel 应用容器，宿主机 `8000` 映射容器 `80`。
- `mysql`：MySQL 8，使用 `mysql_data` volume。
- `redis`：Redis 7，使用 `redis_data` volume。
- `rabbitmq`：RabbitMQ + Management UI，AMQP 端口 `5672`，管理端口 `15672`。
- `mailpit`：本地邮件捕获，Web UI `8025`。

## 高频面试问答

### 镜像和容器有什么区别？

镜像是只读模板，包含文件系统、运行时和启动命令；容器是镜像运行后的进程实例。一个镜像可以启动多个容器。

### 容器内为什么不能用 `127.0.0.1` 连接 MySQL？

容器内的 `127.0.0.1` 指向当前容器自己，不是 MySQL 容器。Compose 默认网络会用 service name 做 DNS，因此应用容器里应使用 `DB_HOST=mysql`。

### volume 解决什么问题？

容器删除后容器层数据会丢。volume 用于持久化数据库、Redis、RabbitMQ 等状态数据。`docker compose down` 保留 volume，`docker compose down -v` 删除 volume。

### 本地开发镜像和生产镜像有什么区别？

本地开发关注调试效率、热更新和工具完整；生产镜像关注体积、安全、启动速度、稳定性、可观测性和不可变发布。

### Docker 能否解决所有部署问题？

不能。Docker 解决运行环境一致性，但密钥管理、配置管理、数据库迁移、日志采集、健康检查、回滚和容量评估仍要单独设计。

## 常用命令

```bash
docker compose build laravel.test
docker compose up -d
docker compose up -d --build
docker compose ps
docker compose logs -f laravel.test
docker compose exec laravel.test bash
docker compose exec laravel.test php artisan migrate
docker compose down
docker compose down -v
```

## 生产实践提示

- 用 `.dockerignore` 减小构建上下文。
- 生产密钥不要写入镜像层。
- 容器日志输出到 stdout/stderr，交给平台采集。
- healthcheck 要覆盖应用入口和关键依赖。
- `storage` 和 `bootstrap/cache` 权限要在镜像或启动流程中处理。
- 数据库迁移要纳入发布顺序和回滚策略。
