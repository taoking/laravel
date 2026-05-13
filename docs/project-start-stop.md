# 项目启动与关闭

本文档记录当前 Laravel 学习项目的启动、验证、关闭和排错方式。后续任务默认优先使用 Docker Compose 环境。

## 当前环境基线

- 项目目录：`/Users/tao/workspace/code/laravel/laravel`
- 应用地址：`http://127.0.0.1:8000`
- Mailpit：`http://127.0.0.1:8025`
- RabbitMQ Management：`http://127.0.0.1:15672`
- PHP：8.3 Docker 容器
- MySQL：8.0 容器
- Redis：7 容器
- RabbitMQ：3 management 容器

## 首次启动 Docker 环境

进入项目目录：

```bash
cd /Users/tao/workspace/code/laravel/laravel
```

如果没有 `.env`，复制环境文件：

```bash
cp .env.example .env
```

构建 PHP 运行镜像：

```bash
docker compose build laravel.test
```

安装 PHP 依赖：

```bash
docker compose run --rm laravel.test composer install
```

生成应用密钥：

```bash
docker compose run --rm laravel.test php artisan key:generate
```

启动服务：

```bash
docker compose up -d
```

执行迁移：

```bash
docker compose exec laravel.test php artisan migrate
```

## 日常启动

```bash
cd /Users/tao/workspace/code/laravel/laravel
docker compose up -d
```

如果修改了 Dockerfile 或 compose 配置，重新构建：

```bash
docker compose up -d --build
```

## 验证服务

查看容器状态：

```bash
docker compose ps
```

验证 Laravel 环境：

```bash
docker compose exec laravel.test php artisan about --only=environment
```

验证 HTTP 入口：

```bash
curl -I http://127.0.0.1:8000
```

运行测试：

```bash
docker compose exec laravel.test php artisan test
```

验证学习基础设施：

```bash
docker compose exec laravel.test php artisan interview:infra-check
```

运行 Docker 学习专题：

```bash
docker compose exec laravel.test php artisan interview:docker
```

运行 MySQL 学习专题：

```bash
docker compose exec laravel.test php artisan interview:mysql
```

运行 PHP 新特性专题：

```bash
docker compose exec laravel.test php artisan interview:php-features
```

运行 PHP 运行机制专题：

```bash
docker compose exec laravel.test php artisan interview:php-runtime
```

运行 Redis 学习专题：

```bash
docker compose exec laravel.test php artisan interview:redis
```

运行 Laravel Queue 学习专题：

```bash
docker compose exec laravel.test php artisan interview:queue
```

运行 RabbitMQ 学习专题：

```bash
docker compose exec laravel.test php artisan interview:rabbitmq
```

运行 MQ 选型对比专题：

```bash
docker compose exec laravel.test php artisan interview:mq-compare
```

运行 PHP 进程模型专题：

```bash
docker compose exec laravel.test php artisan interview:process
```

运行线上故障复盘专题：

```bash
docker compose exec laravel.test php artisan interview:troubleshoot
```

运行安全面试清单：

```bash
docker compose exec laravel.test php artisan interview:security
```

运行系统设计面试专题：

```bash
docker compose exec laravel.test php artisan interview:system-design
```

查看路由：

```bash
docker compose exec laravel.test php artisan route:list
```

## 关闭项目

停止容器但保留数据卷：

```bash
docker compose down
```

停止容器并删除 MySQL、Redis、RabbitMQ 数据卷：

```bash
docker compose down -v
```

## 常用排错

查看应用日志：

```bash
docker compose exec laravel.test tail -n 100 storage/logs/laravel.log
```

查看容器日志：

```bash
docker compose logs -f laravel.test
docker compose logs -f mysql
docker compose logs -f redis
docker compose logs -f rabbitmq
```

清理 Laravel 缓存：

```bash
docker compose exec laravel.test php artisan optimize:clear
```

重新安装依赖：

```bash
docker compose run --rm laravel.test composer install
```

如果容器内连接 MySQL 失败，确认 `.env` 中使用的是容器服务名：

```dotenv
DB_HOST=mysql
DB_USERNAME=laravel
DB_PASSWORD=secret
```

如果当前本机已有旧 `.env`，可以按 `.env.example` 同步 Docker 相关配置。

## 可选：启用面试样例路由

本项目的面试样例默认不接入业务入口。如需本地访问 `/interview-examples`，先参考 `docs/laravel-interview-examples.md` 注册示例 Provider，并在 `routes/web.php` 中按需引入 `routes/interview_examples.php`。

然后在 `.env` 中开启：

```dotenv
INTERVIEW_EXAMPLES_ENABLED=true
INTERVIEW_EXAMPLES_TOKEN=demo-token
```

启用后验证：

```bash
docker compose exec laravel.test php artisan route:list --path=interview-examples
curl http://127.0.0.1:8000/interview-examples
```

## 备用：本机 PHP 启动

如果临时不使用 Docker，可以使用本机 PHP 启动。该方式需要本机自行准备 PHP 扩展、Composer、MySQL、Redis 等依赖。

```bash
cd /Users/tao/workspace/code/laravel/laravel
composer install
php artisan key:generate
php artisan serve --host=127.0.0.1 --port=8000
```

本机方式只作为备用路径。长期学习、复盘和面试示例验证优先使用 Docker Compose 环境。
