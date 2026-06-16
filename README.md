# Laravel 13 资深 PHP 学习项目

这是一个用于长期学习、复盘和资深 PHP/Laravel 面试准备的 Laravel 13 项目。项目主题是“多数据源指标分析与内容管理平台”，通过真实代码把 PHP 语言机制、Laravel 框架源码、MySQL、Redis、Queue、Kafka、安全、性能、Docker 部署和工程质量串起来。

详细介绍见：[项目介绍文档](docs/project-introduction.md)

## 快速入口

- 后台首页：`http://127.0.0.1:8000/admin`
- 登录页：`http://127.0.0.1:8000/login`
- Swagger UI：`http://127.0.0.1:8000/docs/api`
- OpenAPI YAML：`http://127.0.0.1:8000/docs/openapi.yaml`
- 学习索引：[docs/learning-index.md](docs/learning-index.md)
- 完成度审计：[docs/development-completion-review.md](docs/development-completion-review.md)

## 本地运行

```bash
composer install
npm install
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

本地种子账号：

| 角色 | 邮箱 | 密码 |
| --- | --- | --- |
| 超级管理员 | `admin@example.com` | `password` |
| 分析师 | `analyst@example.com` | `password` |

## 质量门禁

```bash
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
```

当前 P0-P3 计划项已完成。后续扩展应先阅读 [当前项目审计](docs/current-project-audit.md) 和 [架构师面试覆盖度计划](docs/interview/architect-interview-coverage-plan.md)，再新增 P4 任务。

## Laravel Design Patterns Lab

本项目新增了一个设计模式练习模块：PatternLab。

访问页面：

```bash
php artisan serve
http://127.0.0.1:8000/pattern-lab
```

可用命令：

```bash
php artisan pattern-lab:list
php artisan pattern-lab:show strategy
php artisan pattern-lab:next
```

总览文档目录：

```text
docs/pattern-lab/
```

每个模式的文档和练习代码目录：

```text
app/PatternLab/
```

每个具体模式目录中包含：

```text
PATTERN.md
Exercise.php
README.md
TODO.md
```

注意：

本模块只提供设计模式说明、场景、TODO、练习骨架和测试骨架，不提供完整实现。具体设计模式代码需要学习者自己完成。
